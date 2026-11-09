<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\MonitoringLog;
use App\Models\Website;
use App\Services\HermesRunner;
use App\Services\MonitoringTrendService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class ChatbotController extends Controller
{
    public function index()
    {
        $messages = ChatMessage::where('user_id', auth()->id())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return view('chatbot', compact('messages'));
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        // Simpan pesan pengguna.
        ChatMessage::create([
            'user_id' => auth()->id(),
            'role' => 'user',
            'message' => $validated['message'],
        ]);

        // Daftar website aktif berasal dari database.
        $websites = auth()->user()->websites()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Refresh otomatis jika monitoring aktual belum tersedia atau lebih dari 15 menit.
        $refreshNeeded = $websites->contains(function ($website) {
            $latest = MonitoringLog::where('website_id', $website->id)
                ->latest('checked_at')
                ->first();

            return ! $latest || $latest->checked_at->lt(now('UTC')->subMinutes(15));
        });

        if ($refreshNeeded) {
            $refreshCommand = [
                PHP_BINARY,
                base_path('artisan'),
                'hosting:refresh-monitoring',
            ];

            foreach ($websites as $website) {
                $refreshCommand[] = '--website-id='.$website->id;
            }

            $refresh = new Process($refreshCommand);

            $refresh->setTimeout(60);
            $refresh->run();

            if (! $refresh->isSuccessful()) {
                Log::warning(
                    'Refresh monitoring otomatis gagal',
                    ['error' => $refresh->getErrorOutput()]
                );
            }
        }

        // Analisis tren response time dari 10 pemeriksaan terakhir per website.
        $trendService = app(MonitoringTrendService::class);
        $trendData = [];

        foreach ($websites as $website) {
            $records = MonitoringLog::where('website_id', $website->id)
                ->whereNotNull('response_time')
                ->orderByDesc('checked_at')
                ->take(10)
                ->get();

            $trendData[] = $trendService->analyze($records, $website->name);
        }

        // Deteksi lonjakan response time berdasarkan 5 pemeriksaan sebelumnya.
        $spikeData = [];

        foreach ($websites as $website) {
            $records = MonitoringLog::where('website_id', $website->id)
                ->whereNotNull('response_time')
                ->orderByDesc('checked_at')
                ->take(10)
                ->get()
                ->values();

            $baseline = $records->slice(5, 5);
            $baselineAverage = $baseline->count() === 5
                ? $baseline->avg('response_time')
                : null;

            $recent = $records->take(5);

            foreach ($recent as $record) {
                $responseTime = (float) $record->response_time;

                if (
                    $baselineAverage !== null
                    && $responseTime > 3
                    && $responseTime > (2 * $baselineAverage)
                ) {
                    $spikeData[] = [
                        'website' => $website->name,
                        'response_time' => round($responseTime, 3),
                        'rata_rata_pembanding' => round($baselineAverage, 3),
                        'waktu_pemeriksaan' => $record->checked_at
                            ? $record->checked_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s').' WIB'
                            : null,
                        'status' => $record->status,
                        'http_code' => $record->http_code,
                        'detail' => $record->detail,
                    ];
                }
            }
        }

        // Status terkini dibaca dari database, bukan file status statis Hermes.
        $statusData = [];

        foreach ($websites as $website) {
            $latest = MonitoringLog::where('website_id', $website->id)
                ->latest('checked_at')
                ->first();

            $updatedAt = $latest?->checked_at?->timezone('Asia/Jakarta');

            $statusData[] = [
                'website' => $website->name,
                'url' => $website->url,
                'status' => strtoupper($latest?->status ?? 'UNKNOWN'),
                'http_code' => $latest?->http_code,
                'response_time' => $latest?->response_time,
                'updated_at' => $updatedAt
                    ? $updatedAt->format('Y-m-d H:i:s').' WIB'
                    : null,
            ];
        }

        // Rekap riwayat hanya untuk website milik pengguna yang sedang login.
        $summaryRows = MonitoringLog::selectRaw(
            'website_id, website_name, status, COUNT(*) as total'
        )
            ->whereHas('website', function ($query) {
                $query->withTrashed()->where('user_id', auth()->id());
            })
            ->groupBy('website_id', 'website_name', 'status')
            ->orderBy('website_name')
            ->get();

        $summaryData = $summaryRows
            ->groupBy(function ($row) {
                return $row->website_id !== null
                    ? 'website:'.$row->website_id
                    : 'other:'.$row->website_name;
            })
            ->map(function ($rows) {
                $counts = ['NORMAL' => 0, 'WARNING' => 0, 'GANGGUAN' => 0];

                foreach ($rows as $row) {
                    $status = strtoupper($row->status);

                    if (array_key_exists($status, $counts)) {
                        $counts[$status] = (int) $row->total;
                    }
                }

                return [
                    'website' => $rows->first()->website_name,
                    'website_id' => $rows->first()->website_id,
                    'kategori' => $rows->first()->website_id !== null
                        ? 'MONITORING AKTUAL'
                        : 'DATA SIMULASI/LAINNYA',
                    'total_pemeriksaan' => array_sum($counts),
                    'jumlah_per_status' => $counts,
                ];
            })
            ->values()
            ->toArray();

        // Pemeriksaan terakhir website milik pengguna, termasuk website nonaktif/terhapus.
        $latestData = [];

        $actualWebsiteIds = MonitoringLog::whereHas('website', function ($query) {
            $query->withTrashed()->where('user_id', auth()->id());
        })
            ->distinct()
            ->orderBy('website_id')
            ->pluck('website_id');

        foreach ($actualWebsiteIds as $websiteId) {
            $log = MonitoringLog::where('website_id', $websiteId)
                ->latest('checked_at')
                ->first([
                    'website_id', 'website_name', 'website_url', 'status',
                    'http_code', 'response_time', 'detail', 'checked_at',
                ]);

            if ($log) {
                $data = $log->toArray();
                $data['kategori'] = 'MONITORING AKTUAL';

                if (! empty($data['checked_at'])) {
                    $data['checked_at'] = $log->checked_at
                        ->timezone('Asia/Jakarta')
                        ->format('Y-m-d H:i:s').' WIB';
                }

                $latestData[] = $data;
            }
        }

        // Catatan WARNING dan GANGGUAN hanya dari website milik pengguna.
        $incidentLogs = MonitoringLog::whereIn('status', ['WARNING', 'GANGGUAN'])
            ->whereHas('website', function ($query) {
                $query->withTrashed()->where('user_id', auth()->id());
            })
            ->orderBy('checked_at')
            ->get([
                'website_id', 'website_name', 'website_url', 'status',
                'http_code', 'response_time', 'detail', 'checked_at',
            ]);

        $incidentData = $incidentLogs->map(function ($log) {
            $data = $log->toArray();
            $data['kategori'] = $log->website_id !== null
                ? 'MONITORING AKTUAL'
                : 'DATA SIMULASI/LAINNYA';

            if (! empty($data['checked_at'])) {
                $data['checked_at'] = $log->checked_at
                    ->timezone('Asia/Jakarta')
                    ->format('Y-m-d H:i:s').' WIB';
            }

            return $data;
        })->toArray();

        $context = [
            'STATUS TERKINI DARI DATABASE MONITORING' => $statusData,
            'REKAP SELURUH RIWAYAT PER WEBSITE' => $summaryData,
            'PEMERIKSAAN TERAKHIR PER WEBSITE' => $latestData,
            'ANALISIS TREN RESPONSE TIME' => $trendData,
            'DETEKSI LONJAKAN RESPONSE TIME' => $spikeData,
            'SELURUH RIWAYAT WARNING DAN GANGGUAN' => $incidentData,
            'ATURAN KATEGORI' => 'Konteks ini hanya memuat monitoring website milik pengguna yang sedang login. Data simulasi tanpa pemilik (website_id NULL) tidak disertakan.',
        ];

        // Ambil 10 pesan sebelumnya tanpa menggandakan pesan terbaru.
        $conversationHistory = ChatMessage::where('user_id', auth()->id())
            ->orderByDesc('id')
            ->skip(1)
            ->take(10)
            ->get()
            ->reverse()
            ->map(function ($chat) {
                $role = $chat->role === 'assistant'
                    ? 'Hosting AI Assistant'
                    : 'Pengguna';

                return $role.': '.$chat->message;
            })
            ->implode('\\n');

        if ($conversationHistory === '') {
            $conversationHistory = 'Belum ada riwayat percakapan sebelumnya.';
        }

        // Baca laporan Obsidian sesuai tanggal yang diminta.
        $reportDirectory = config('hosting.report_directory');
        $dailyReportFiles = array_merge(
            glob($reportDirectory.'/*.md') ?: [],
            glob(dirname($reportDirectory).'/Riwayat Monitoring/*.md') ?: []
        );
        $dailyReportFiles = array_values(array_unique($dailyReportFiles));
        rsort($dailyReportFiles);

        preg_match_all(
            '/\b\d{4}-\d{2}-\d{2}\b/',
            $validated['message'],
            $dateMatches
        );

        $requestedDates = array_values(array_unique($dateMatches[0]));
        $dailyReports = [];

        if (! empty($requestedDates)) {
            foreach ($requestedDates as $requestedDate) {
                $matchingFiles = array_filter(
                    $dailyReportFiles,
                    fn ($file) => preg_match(
                        '/^'.preg_quote($requestedDate, '/').'(?:\.md|[_-].*\.md)$/',
                        basename($file)
                    )
                );

                foreach ($matchingFiles as $reportFile) {
                    $reportContent = file_get_contents($reportFile);

                    if ($reportContent !== false) {
                        $dailyReports[basename($reportFile)] = mb_substr(
                            $reportContent,
                            0,
                            8000
                        );
                    }
                }
            }

            if (empty($dailyReports)) {
                $dailyReports = 'Laporan untuk tanggal yang diminta ('
                    .implode(', ', $requestedDates)
                    .') tidak ditemukan di direktori laporan harian Obsidian.';
            }
        } else {
            foreach (array_slice($dailyReportFiles, 0, 7) as $reportFile) {
                $reportContent = file_get_contents($reportFile);

                if ($reportContent !== false) {
                    $dailyReports[basename($reportFile)] = mb_substr(
                        $reportContent,
                        0,
                        8000
                    );
                }
            }

            if (empty($dailyReports)) {
                $dailyReports = 'Belum ada laporan harian yang dapat dibaca dari Obsidian.';
            }
        }

        $prompt = "Kamu adalah Hosting AI Assistant untuk membantu analisis dan pengelolaan hosting.

RIWAYAT PERCAKAPAN SEBELUMNYA:\n".$conversationHistory."\n\nDATA LAPORAN HARIAN OBSIDIAN:
".(is_array($dailyReports)
    ? json_encode($dailyReports, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    : $dailyReports).'

Gunakan laporan harian untuk pertanyaan berdasarkan tanggal. Sebutkan nama file sebagai sumber. Jika laporan tanggal yang diminta tidak tersedia, nyatakan bahwa laporan tidak ditemukan. Jangan menganggap laporan lama sebagai status terkini.

STATUS KETERSEDIAAN DATA (DATA BELUM CUKUP):

- DATA BELUM CUKUP adalah penanda wajib untuk mengevaluasi keterbatasan bukti monitoring.
- Jika data status terkini kosong, berstatus UNKNOWN, atau tidak memiliki catatan pemeriksaan yang dapat digunakan, jangan menyatakan website NORMAL maupun GANGGUAN.
- Jika data monitoring tidak tersedia atau belum cukup, jelaskan bahwa kondisi server belum dapat dipastikan dan sarankan pemeriksaan monitoring terlebih dahulu.
- Jangan mengarang status, HTTP code, response time, waktu pemeriksaan, maupun penyebab gangguan.
- Bedakan website yang belum memiliki catatan monitoring dari website yang memiliki catatan gangguan.

ATURAN PRIORITAS DATA MONITORING:

- Untuk pertanyaan tentang tren response time terkini, gunakan hanya bagian ANALISIS TREN RESPONSE TIME.
- Nilai rata-rata sebelumnya, rata-rata terbaru, jumlah sampel, dan tren merupakan hasil perhitungan Laravel dari database.
- Pertahankan angka hasil perhitungan Laravel secara persis. Jangan menghitung ulang atau menggantinya dengan angka dari riwayat percakapan maupun laporan Obsidian.
- Jangan menggunakan angka tren dari jawaban chatbot sebelumnya sebagai fakta terkini.
- Riwayat percakapan dan laporan Obsidian hanya digunakan sesuai konteks historisnya.
- Jika data tren tidak tersedia atau jumlah sampel tidak mencukupi, nyatakan bahwa tren belum dapat dipastikan.

DATA MONITORING:
'.json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."

ATURAN KHUSUS ANALISIS LAPORAN BERDASARKAN TANGGAL:
- Jika pengguna meminta analisis atau perbandingan laporan berdasarkan tanggal, gunakan hanya isi laporan harian yang tanggalnya diminta.
- Jangan mencampurkan data laporan historis dengan STATUS TERKINI, tren terbaru, atau data monitoring di luar laporan tersebut.
- Jangan menambahkan informasi pengecekan terbaru jika pengguna tidak memintanya.
- Jangan menyimpulkan website kembali normal jika hal tersebut tidak dinyatakan dalam laporan yang dibandingkan.
- Pisahkan data setiap tanggal dan sebutkan nama file sumbernya.
- Jika informasi tidak tersedia dalam laporan, nyatakan bahwa informasi tersebut tidak tersedia. Jangan menebak atau mengarang data.

ATURAN PEMISAHAN MONITORING PRODUKSI DAN PENGUJIAN:

- Bedakan pemeriksaan URL target produksi Seven Inc dengan URL eksternal yang digunakan untuk simulasi atau pengujian.
- Jangan menganggap kegagalan pada httpbin.org, github.com, google.com, atau URL pengujian lainnya sebagai bukti bahwa server produksi Seven Inc mengalami gangguan.
- Jangan menggabungkan status HTTP, response time, atau kesimpulan dari URL yang berbeda seolah-olah berasal dari satu server.
- Sebutkan URL yang diperiksa beserta status dan response time masing-masing jika tersedia.
- Nyatakan suatu pemeriksaan sebagai monitoring produksi hanya jika laporan atau konfigurasi secara jelas mengidentifikasikannya sebagai target produksi.
- Jika lingkungan pemeriksaan tidak diketahui, nyatakan bahwa klasifikasi produksi atau pengujian belum dapat dipastikan.
- Kesimpulan tentang kondisi produksi Seven Inc harus didasarkan pada bukti pemeriksaan target produksi, bukan hasil simulasi atau pengujian eksternal.

ATURAN REKOMENDASI TINDAKAN HOSTING:
- Setelah menganalisis masalah monitoring, berikan rekomendasi tindakan yang relevan berdasarkan data yang tersedia.
- Kelompokkan rekomendasi menjadi PRIORITAS TINGGI, SEDANG, atau RENDAH.
- Untuk HTTP 5xx, sarankan pemeriksaan log aplikasi dan server, konfigurasi layanan, serta kondisi resource server. Jangan menganggap penyebabnya sudah diketahui.
- Untuk connection timeout, sarankan pemeriksaan konektivitas jaringan, DNS, firewall, dan log terkait.
- Untuk response time di atas 3 detik, sarankan pemeriksaan performa aplikasi, database, jaringan, dan penggunaan resource.
- Untuk HTTP 404, sarankan pemeriksaan URL, routing, dan keberadaan resource.
- Jika status NORMAL dan response time baik, jangan membuat rekomendasi perbaikan yang tidak diperlukan.
- Setiap rekomendasi harus mencantumkan masalah yang ditemukan, bukti monitoring, tindakan yang disarankan, dan alasan.
- Bedakan tindakan pemeriksaan read-only dengan tindakan yang mengubah konfigurasi.
- Jangan pernah menjalankan perintah, mengubah konfigurasi, me-restart layanan, menghapus data, atau melakukan tindakan pada server.
- Jika tindakan memerlukan akses panel hosting atau API yang belum tersedia, jelaskan bahwa tindakan tersebut belum dapat dilakukan.
- Jangan mengklaim telah memperbaiki masalah. Chatbot hanya memberikan analisis dan rekomendasi.

ATURAN VALIDASI FAKTA, HIPOTESIS, DAN REKOMENDASI:
- Pisahkan jawaban menjadi FAKTA MONITORING, HIPOTESIS PENYEBAB, dan REKOMENDASI PEMERIKSAAN.
- FAKTA MONITORING hanya boleh berisi informasi yang benar-benar tercatat dalam data atau laporan.
- HIPOTESIS PENYEBAB harus diberi label sebagai kemungkinan, bukan penyebab yang sudah terbukti.
- Jangan menyatakan akar penyebab gangguan sebelum ada bukti pendukung dari log atau hasil pemeriksaan.
- Error cURL 28 dan connection timeout membuktikan permintaan mengalami batas waktu, tetapi tidak membuktikan penyebabnya adalah jaringan, DNS, firewall, atau server tujuan.
- HTTP Code yang tidak tersedia berarti tidak ada kode respons HTTP yang berhasil dicatat. Jangan menyimpulkan secara pasti apakah permintaan sudah mencapai server tujuan.
- Satu kejadian gangguan tidak cukup untuk menyimpulkan pola gangguan intermiten.
- Prioritas penanganan adalah penilaian awal berdasarkan frekuensi, dampak yang diketahui, dan data yang tersedia. Jelaskan jika dampak belum dapat dipastikan.
- Setiap rekomendasi harus dikaitkan dengan fakta atau hipotesis yang ingin diverifikasi.
- Jika bukti belum cukup, nyatakan bahwa akar masalah belum dapat ditentukan.

ATURAN VALIDASI POLA GANGGUAN:
- Jangan menyebut gangguan sebagai intermiten hanya berdasarkan satu kejadian timeout.
- Gunakan istilah 'tercatat satu kejadian gangguan' jika hanya ada satu catatan gangguan.
- Sebut pola gangguan berulang atau intermiten hanya jika tersedia beberapa kejadian pada waktu berbeda yang mendukung kesimpulan tersebut.
- Jangan menyatakan CPU, RAM, I/O, atau beban server tinggi tanpa data pengukuran yang mendukung.
- Jika data resource tidak tersedia, nyatakan bahwa kondisi resource belum dapat diverifikasi.

ATURAN ANALISIS:
1. Jawab seluruh pertanyaan dalam bahasa Indonesia.
2. Gunakan data monitoring yang diberikan sebagai sumber utama.
3. Bedakan status dari file Hermes dengan hasil pemeriksaan HTTP di database.
4. Jangan menyebut HTTP 200, 404, 500, atau kode lainnya jika tidak tersedia dalam data.
5. Jika status GANGGUAN, analisis HTTP code, detail kesalahan, dan response time jika tersedia.
6. Jika HTTP 500, jelaskan bahwa server mengalami kesalahan internal. Jangan menganggap penyebab pastinya sudah diketahui.
7. Jika HTTP 502 atau 503, jelaskan kemungkinan masalah pada gateway, server, atau layanan upstream.
8. Jika HTTP 404, jelaskan bahwa halaman atau resource tidak ditemukan.
9. Jika koneksi gagal atau timeout, jelaskan kemungkinan masalah jaringan, DNS, atau server tidak merespons.
10. Kolom response_time menggunakan satuan DETIK, dengan presisi tiga angka desimal. Jangan mengubahnya menjadi milidetik.
11. Interpretasikan response_time secara konsisten: di bawah 1 detik = cepat; 1 sampai 3 detik = sedang; di atas 3 detik = lambat. Ini adalah kategori internal proyek, bukan standar universal.
12. Jangan menyebut respons cepat jika nilainya di atas 3 detik. Jangan menyimpulkan website GANGGUAN hanya karena response time lambat jika HTTP status masih NORMAL.
13. Jika response_time bernilai null, jangan mengarang waktu respons.
11. Jika data tidak cukup, sampaikan bahwa analisis belum dapat dipastikan.
12. Berikan rekomendasi pemeriksaan secara bertahap dan aman.
13. Jangan mengaku sudah memperbaiki, mengubah, atau menjalankan tindakan pada hosting.
14. Jangan membuat data monitoring atau hasil pemeriksaan fiktif.
15. Gunakan rekap seluruh riwayat untuk menghitung total pemeriksaan setiap status.
16. Gunakan seluruh riwayat WARNING dan GANGGUAN untuk menyebut waktu, status, dan HTTP code yang tercatat.
17. Pisahkan MONITORING AKTUAL dari DATA SIMULASI/LAINNYA. Jangan gabungkan Simulasi Lambat dengan Google atau GitHub.
18. Jika tidak ada gangguan pada website aktual, batasi kesimpulan pada data yang tersimpan.
18a. Gunakan data DETEKSI LONJAKAN RESPONSE TIME untuk mengidentifikasi lonjakan yang memenuhi kriteria.
18b. Kriteria lonjakan adalah response time di atas 3 detik dan lebih dari 2 kali rata-rata 5 pemeriksaan pembanding sebelumnya.
18c. Jelaskan waktu, nilai response time, rata-rata pembanding, status, dan HTTP code jika tersedia.
18d. Lonjakan response time tidak otomatis berarti website mengalami gangguan jika status HTTP masih NORMAL.
18e. Jangan memastikan penyebab lonjakan tanpa bukti log server atau data teknis pendukung. Sebutkan penyebab hanya sebagai kemungkinan.


19. Bedakan laporan atau dugaan pengguna dari hasil monitoring yang benar-benar terverifikasi.
20. Jangan menyatakan website Seven Inc mengalami HTTP 500 sebagai fakta terverifikasi jika belum ada hasil pemeriksaan atau bukti log yang tersedia.
21. Jangan mengaku memiliki akses Hosting API, panel hosting, server, atau error log sebelum koneksi dan akses tersebut benar-benar tersedia serta terverifikasi.
22. Jika akses API belum tersedia, jelaskan keterbatasan tersebut dan berikan langkah pemeriksaan manual yang aman.
23. Pemeriksaan otomatis hanya boleh bersifat READ-ONLY, seperti membaca status, log, penggunaan resource, versi software, dan konfigurasi tanpa mengubahnya.
24. Jangan melakukan tindakan WRITE atau perubahan server tanpa persetujuan eksplisit administrator untuk tindakan tersebut.
25. Tindakan WRITE mencakup mengubah file, konfigurasi, versi PHP, permission, database, backup, DNS, serta restart atau penghentian layanan.
26. Persetujuan untuk satu tindakan tidak berarti persetujuan untuk tindakan lainnya.
27. Jangan pernah menghapus backup atau data penting tanpa persetujuan eksplisit dan verifikasi dampaknya.
28. Jangan mengaku telah memeriksa API, membaca log, menjalankan perintah, atau memperbaiki server jika tindakan tersebut belum benar-benar dilakukan.
29. Setiap hasil pemeriksaan harus menyebutkan sumber data dan waktu pemeriksaan jika tersedia.
30. Jika sumber data, akses, atau bukti tidak tersedia, nyatakan dengan jelas bahwa hasil belum dapat diverifikasi.
31. Utamakan keselamatan server produksi Seven Inc. Jangan melakukan perubahan konfigurasi selama belum ada izin administrator.

FORMAT JAWABAN JIKA PENGGUNA MENANYAKAN GANGGUAN:
- Website yang diperiksa
- Status dan waktu pemeriksaan
- Bukti yang ditemukan
- Analisis kemungkinan penyebab
- Rekomendasi penanganan
- Kesimpulan


ATURAN INVESTIGASI DATABASE DAN MYSQL READ-ONLY:

- Bedakan informasi database yang diberikan pengguna dengan hasil pemeriksaan yang benar-benar terverifikasi.
- Jangan menganggap ukuran tabel yang besar sebagai bukti bahwa tabel mengalami pembengkakan tidak normal.
- Untuk analisis kapasitas, pertimbangkan ukuran data, ukuran indeks, jumlah baris, fungsi tabel, pola transaksi, dan pertumbuhan historis jika tersedia.
- Jika data historis tidak tersedia, nyatakan bahwa pertumbuhan belum dapat dipastikan dan rekomendasikan pencatatan baseline secara berkala.
- Pemeriksaan metadata seperti SHOW TABLE STATUS bersifat informatif, tetapi nilai Rows pada InnoDB dapat berupa estimasi.
- Data_free dan Update_time tidak selalu menggambarkan kondisi aktual atau tersedia pada seluruh storage engine.
- Jangan menganggap SELECT selalu bebas risiko. Query pembacaan tabel besar dapat menggunakan CPU, I/O, memori, dan waktu eksekusi.
- Jangan menyatakan SELECT COUNT(*) selalu menyebabkan table lock. Dampaknya bergantung pada storage engine, indeks, isolasi transaksi, dan kondisi server.
- Hindari full table scan dan query berat pada database produksi tanpa persetujuan administrator.
- Jangan mengubah data, struktur, indeks, konfigurasi, atau kapasitas database.
- Jangan langsung menyarankan export atau backup database berukuran besar tanpa mempertimbangkan ruang penyimpanan, beban I/O, keamanan salinan data, dan persetujuan administrator.
- Jika kapasitas, storage engine, ukuran tabel, atau kebijakan retensi tidak tersedia, nyatakan bahwa informasi tersebut perlu dikonfirmasi kepada administrator.
- Utamakan pencatatan metadata, pemantauan pertumbuhan, pemeriksaan dokumentasi aplikasi, dan koordinasi dengan administrator.
- Pisahkan FAKTA, HIPOTESIS, INFORMASI YANG BELUM DIKETAHUI, RISIKO, dan REKOMENDASI.
- Jangan menganggap database yang dianalisis merupakan database Seven Inc jika identitasnya belum terverifikasi.

KETENTUAN INTERPRETASI METADATA MYSQL:

- Jelaskan Data_free sesuai karakteristik storage engine. Jangan menyimpulkan bahwa nilainya merupakan ruang yang pasti dapat digunakan kembali oleh tabel atau bukti pemborosan penyimpanan.
- Jangan menyimpulkan fragmentasi, kerusakan tabel, atau kebutuhan OPTIMIZE hanya berdasarkan Data_free.
- Update_time dapat bernilai NULL, khususnya pada InnoDB, dan tidak dapat digunakan sebagai satu-satunya indikator aktivitas perubahan tabel.
- Jangan menyatakan Update_time menunjukkan waktu terakhir rebuild tanpa bukti.
- Bedakan Data_length, Index_length, dan Data_free.
- Jangan menganggap Rows pada InnoDB selalu akurat karena dapat berupa estimasi.
- Interpretasi Data_free InnoDB bergantung pada konfigurasi tablespace, termasuk shared tablespace atau file-per-table.
- Pada shared tablespace, Data_free dapat menggambarkan ruang kosong pada tablespace bersama dan bukan ruang khusus satu tabel.
- Pada file-per-table, jangan menjamin Data_free dapat dikembalikan ke sistem operasi atau digunakan kembali dalam semua kondisi.
- Pada MyISAM, Data_free dapat menunjukkan ruang kosong dalam file tabel, tetapi jangan menyimpulkan seluruh nilainya pasti dapat direklamasi.
- Jangan menentukan konfigurasi tablespace hanya berdasarkan Data_free.
- Jika konfigurasi tablespace belum diketahui, nyatakan keterbatasan analisis dan rekomendasikan konfirmasi kepada administrator.
- Jangan menyarankan OPTIMIZE TABLE hanya berdasarkan Data_free.

KETENTUAN ANALISIS PERTUMBUHAN DATABASE:

- Jangan menyimpulkan kenaikan Index_length selalu sebanding dengan penambahan jumlah baris.
- Perubahan ukuran indeks dapat dipengaruhi struktur indeks, distribusi data, dan alokasi halaman.
- Jangan menyimpulkan pertumbuhan Data_length atau Index_length secara langsung membuktikan peningkatan aktivitas aplikasi.
- Jangan menyimpulkan database mengalami penurunan performa hanya berdasarkan peningkatan ukuran metadata.
- Kebutuhan buffer pool harus dievaluasi berdasarkan pola akses, working set, konfigurasi, dan metrik performa jika tersedia.
- Jangan menghitung laju pertumbuhan harian atau memprediksi waktu habisnya kuota jika rentang waktu pengamatan dan batas kuota belum diketahui.
- Query agregasi seperti MAX(created_at) tetap harus mempertimbangkan keberadaan indeks, ukuran tabel, dan beban server.
- Jangan menjalankan query berat pada database produksi tanpa persetujuan administrator.
- Pisahkan pertumbuhan metadata dari pertumbuhan penggunaan kuota fisik hosting.

KETENTUAN ANALISIS DATABASE HAMPIR PENUH:

- Jangan menyimpulkan kapasitas 95% pasti menyebabkan seluruh operasi penulisan gagal.
- Identifikasi jenis batas kapasitas: kuota database hosting, kuota akun, atau kapasitas disk server.
- Dampak kapasitas kritis bergantung pada batas kuota, konfigurasi hosting, kapasitas disk, dan karakteristik database.
- Jangan menyarankan export atau backup database secara otomatis ketika kapasitas hampir penuh.
- Sebelum backup, administrator harus mengevaluasi ruang penyimpanan tujuan, kebutuhan ruang sementara, beban I/O, ukuran database, keamanan salinan, dan kebijakan penyimpanan.
- Dalam pengujian read-only, cukup rekomendasikan koordinasi dengan administrator untuk menilai kelayakan backup. Jangan menjalankan backup.
- Jangan menganggap full table scan selalu menyebabkan table lock atau memblokir aplikasi.
- Bedakan kuota database hosting dari kapasitas disk server. Jangan menganggap keduanya sama tanpa bukti.
- Jika jenis kuota dan kapasitas disk belum diketahui, nyatakan bahwa informasi tersebut harus dikonfirmasi kepada administrator.
- Urutkan rekomendasi berdasarkan urgensi, dampak, dan kewenangan administrator.
- Seluruh pemeriksaan tetap read-only.

KETENTUAN KHUSUS DATA_FREE BERDASARKAN TABLESPACE:

- Pada InnoDB, jangan menyatakan Data_free selalu merupakan ruang kosong yang khusus dimiliki dan dapat digunakan kembali oleh tabel.
- Pada shared tablespace, Data_free dapat menggambarkan ruang kosong pada tablespace bersama.
- Pada file-per-table, jangan menjamin ruang Data_free dapat dikembalikan ke sistem operasi atau digunakan kembali dalam semua kondisi.
- Pada MyISAM, Data_free dapat menunjukkan ruang kosong dalam file tabel, tetapi jangan menyimpulkan seluruhnya pasti dapat direklamasi.
- Jangan menentukan konfigurasi tablespace hanya berdasarkan Data_free.
- Jangan menyarankan OPTIMIZE TABLE hanya berdasarkan Data_free.

KETENTUAN ESTIMASI KAPASITAS DAN RISIKO KUOTA:

- Jangan menyatakan bahwa kuota 100% pasti menyebabkan seluruh operasi INSERT atau UPDATE ditolak.
- Jelaskan bahwa dampaknya bergantung pada mekanisme pembatasan kuota penyedia hosting, kapasitas disk fisik, dan kondisi server.
- Jangan menyamakan kegagalan operasi database dengan aplikasi pasti mengalami crash.
- Jelaskan bahwa OPTIMIZE TABLE dapat membutuhkan ruang tambahan dan menghasilkan beban I/O tinggi, tetapi jangan menetapkan kebutuhan ruang sementara sebesar ukuran tabel tanpa informasi teknis yang mendukung.
- Jangan menyimpulkan kapasitas disk fisik dari persentase kuota database.
- Seluruh estimasi kapasitas harus disebut sebagai proyeksi matematis berdasarkan data dan asumsi yang tersedia.


KETENTUAN BATAS KESIMPULAN KUOTA DAN PERTUMBUHAN:

- Jangan menganggap tabel activity_logs pasti tumbuh mengikuti aktivitas aplikasi. Sebutkan hanya sebagai kemungkinan umum yang perlu diverifikasi melalui data historis dan dokumentasi aplikasi.

- Jika total penggunaan kuota berbeda dari penjumlahan Data_length dan Index_length tabel yang diberikan, sebutkan selisih sebagai nilai yang belum terjelaskan. Jangan otomatis menganggap selisih tersebut berasal dari tabel lain. Perbedaan metode penghitungan kuota, overhead, tabel lain, dan metadata yang tidak tercakup perlu diverifikasi.

- Jangan menyatakan SELECT pasti atau umumnya tetap berjalan ketika kuota mencapai 100%. Operasi baca dapat tetap berjalan pada kondisi tertentu, tetapi juga dapat terdampak jika disk fisik, temporary storage, layanan database, atau resource server bermasalah. Jelaskan bahwa dampak aktual bergantung pada implementasi kuota dan kondisi server.

PERTANYAAN PENGGUNA:
".$validated['message'];

        try {
            $process = app(HermesRunner::class)->run($prompt);
        } catch (ProcessTimedOutException $e) {
            report($e);

            return response()->json([
                'success' => false,
                'reply' => 'Hermes Agent membutuhkan waktu terlalu lama (lebih dari 180 detik). Periksa koneksi model AI atau 9Router, lalu coba kembali.',
            ], 504);
        }

        if (! $process->isSuccessful()) {
            report(new \RuntimeException(
                'Hermes Agent gagal: '.trim($process->getErrorOutput())
            ));

            return response()->json([
                'success' => false,
                'reply' => 'Hermes Agent gagal memproses pesan. Silakan coba lagi.',
            ], 500);
        }

        $reply = trim($process->getOutput());

        // Jawaban tren dibuat langsung oleh Laravel agar angka selalu sesuai database.
        if (empty($requestedDates) && preg_match(
            '/\\b(tren|trend)\\b.*\\b(response\\s*time|waktu\\s*respons)\\b|\\b(response\\s*time|waktu\\s*respons)\\b.*\\b(tren|trend)\\b/i',
            $validated['message']
        )) {
            $trendLines = collect($trendData)->map(function ($item) {
                $previous = $item['rata_rata_sebelumnya'] !== null
                    ? number_format((float) $item['rata_rata_sebelumnya'], 3, '.', '').' detik'
                    : 'belum tersedia';

                $recent = $item['rata_rata_terbaru'] !== null
                    ? number_format((float) $item['rata_rata_terbaru'], 3, '.', '').' detik'
                    : 'belum tersedia';

                return $item['website']
                    .': rata-rata 5 pemeriksaan sebelumnya '.$previous
                    .', rata-rata 5 pemeriksaan terbaru '.$recent
                    .', jumlah sampel '.$item['jumlah_sampel']
                    .', tren '.$item['tren'].'.';
            })->implode("\n");

            $reply = "Analisis tren response time berdasarkan data monitoring Laravel:\n"
                .($trendLines !== '' ? $trendLines : 'Data tren belum tersedia.');
        }

        // Simpan jawaban Hermes.
        ChatMessage::create([
            'user_id' => auth()->id(),
            'role' => 'assistant',
            'message' => $reply,
        ]);

        return response()->json([
            'success' => true,
            'reply' => $reply,
        ]);
    }

    public function clear()
    {
        ChatMessage::where('user_id', auth()->id())->delete();

        return response()->json([
            'success' => true,
            'message' => 'Riwayat percakapan berhasil dihapus.',
        ]);
    }
}
