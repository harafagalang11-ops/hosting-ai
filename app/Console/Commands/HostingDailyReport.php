<?php

namespace App\Console\Commands;

use App\Models\HostingNotification;
use App\Models\MonitoringLog;
use App\Models\Website;
use App\Services\TelegramNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hosting:daily-report {date? : Tanggal laporan format YYYY-MM-DD}')]

#[Description('Membuat laporan monitoring harian ke Obsidian')]

class HostingDailyReport extends Command
{
    public function handle(): int
    {
        $timezone = 'Asia/Jakarta';

        try {
            $date = Carbon::createFromFormat(
                'Y-m-d',
                $this->argument('date') ?: now($timezone)->format('Y-m-d'),
                $timezone
            );

            if (! $date || $date->format('Y-m-d') !== ($this->argument('date') ?: now($timezone)->format('Y-m-d'))) {
                throw new \InvalidArgumentException;
            }
        } catch (\Throwable $e) {
            $this->error('Format tanggal harus YYYY-MM-DD.');

            return self::FAILURE;
        }

        $startUtc = $date->copy()->startOfDay()->utc();
        $endUtc = $date->copy()->endOfDay()->utc();

        $logs = MonitoringLog::whereBetween('checked_at', [$startUtc, $endUtc])
            ->orderBy('checked_at')
            ->get();

        // Ambil website yang masih aktif dan tidak dihapus.
        $activeWebsiteIds = Website::where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Monitoring aktual hanya berasal dari website aktif.
        $actual = $logs->filter(fn ($log) => $log->website_id !== null
            && in_array((int) $log->website_id, $activeWebsiteIds, true)
        );

        // Riwayat website nonaktif, website uji, dan website yang di-soft-delete.
        $inactiveWebsiteHistory = $logs->filter(fn ($log) => $log->website_id !== null
            && ! in_array((int) $log->website_id, $activeWebsiteIds, true)
        );

        // Riwayat Hermes: data lama tanpa website_id.
        $hermesHistory = $logs->filter(fn ($log) => $log->website_id === null
            && str_contains((string) $log->detail, 'Sumber: Hermes Agent')
        );

        // Simulasi: data tanpa website_id yang bukan berasal dari Hermes.
        $simulation = $logs->filter(fn ($log) => $log->website_id === null
            && ! str_contains((string) $log->detail, 'Sumber: Hermes Agent')
        );

        $lines = [
            '# Laporan Monitoring Hosting Harian',
            '',
            '- **Tanggal:** '.$date->format('d-m-Y'),
            '- **Dibuat:** '.now($timezone)->format('d-m-Y H:i:s').' WIB',
            '- **Total pemeriksaan:** '.$logs->count(),
            '',
            '## 1. Monitoring Aktual',
            '',
        ];

        $appendSection = function ($title, $records) use (&$lines, $timezone) {
            $lines[] = '### '.$title;
            $lines[] = '';

            if ($records->isEmpty()) {
                $lines[] = 'Tidak ada data pemeriksaan.';
                $lines[] = '';

                return;
            }

            $lines[] = '| Status | Jumlah |';
            $lines[] = '|---|---:|';

            foreach (['NORMAL', 'WARNING', 'GANGGUAN'] as $status) {
                $lines[] = '| '.$status.' | '.$records->where('status', $status)->count().' |';
            }

            $times = $records->pluck('response_time')->filter(fn ($value) => $value !== null);
            $average = $times->isNotEmpty() ? number_format($times->avg(), 3).' detik' : 'Tidak tersedia';

            $latest = $records->max('checked_at');

            $lines[] = '';
            $lines[] = '- Total pemeriksaan: '.$records->count();
            $lines[] = '- Rata-rata waktu respons: '.$average;
            $lines[] = '- Pemeriksaan terakhir: '.($latest ? Carbon::parse($latest)->timezone($timezone)->format('H:i:s').' WIB' : '-');
            $lines[] = '';
        };

        if ($actual->isEmpty()) {
            $lines[] = 'Tidak ada data monitoring aktual.';
            $lines[] = '';
        } else {
            foreach ($actual->groupBy('website_name') as $name => $records) {
                $appendSection($name, $records);
            }
        }

        $lines[] = '## 2. Riwayat Monitoring Hermes';
        $lines[] = '';

        if ($hermesHistory->isEmpty()) {
            $lines[] = 'Tidak ada riwayat monitoring Hermes.';
            $lines[] = '';
        } else {
            foreach ($hermesHistory->groupBy('website_name') as $name => $records) {
                $appendSection($name, $records);
            }
        }

        $lines[] = '## 3. Data Simulasi dan Pengujian';
        $lines[] = '';

        if ($simulation->isEmpty()) {
            $lines[] = 'Tidak ada data simulasi.';
            $lines[] = '';
        } else {
            foreach ($simulation->groupBy('website_name') as $name => $records) {
                $appendSection($name, $records);
            }
        }

        $lines[] = '## 4. Riwayat Website Nonaktif dan Pengujian';
        $lines[] = '';

        if ($inactiveWebsiteHistory->isEmpty()) {
            $lines[] = 'Tidak ada riwayat website nonaktif atau pengujian.';
            $lines[] = '';
        } else {
            foreach ($inactiveWebsiteHistory->groupBy('website_name') as $name => $records) {
                $appendSection($name, $records);
            }
        }

        $lines[] = '## 5. Analisis Tren Response Time';
        $lines[] = '';

        foreach ($actual->groupBy('website_name') as $name => $records) {
            $times = $records
                ->whereNotNull('response_time')
                ->sortBy('checked_at')
                ->values();

            $lines[] = '### '.$name;

            if ($times->count() < 10) {
                $lines[] = 'Data belum cukup untuk membandingkan dua kelompok pemeriksaan (minimal 10 data).';
                $lines[] = '';

                continue;
            }

            $lastTen = $times->slice(-10)->values();
            $previousFive = $lastTen->slice(0, 5);
            $recentFive = $lastTen->slice(5, 5);

            $previousAverage = $previousFive->avg('response_time');
            $recentAverage = $recentFive->avg('response_time');

            if ($recentAverage > $previousAverage * 1.10) {
                $trend = 'MELAMBAT';
            } elseif ($recentAverage < $previousAverage * 0.90) {
                $trend = 'MEMBAIK';
            } else {
                $trend = 'STABIL';
            }

            $lines[] = '- Rata-rata 5 pemeriksaan sebelumnya: '.number_format($previousAverage, 3).' detik';
            $lines[] = '- Rata-rata 5 pemeriksaan terbaru: '.number_format($recentAverage, 3).' detik';
            $lines[] = '- Kesimpulan tren: **'.$trend.'**';
            $lines[] = '';
        }

        $lines[] = '## 6. Deteksi Lonjakan Response Time';
        $lines[] = '';
        $spikeFound = false;

        foreach ($actual->groupBy('website_name') as $name => $records) {
            $times = $records
                ->whereNotNull('response_time')
                ->sortBy('checked_at')
                ->values();

            if ($times->count() < 10) {
                continue;
            }

            $lastTen = $times->slice(-10)->values();
            $previousFive = $lastTen->slice(0, 5);
            $recentFive = $lastTen->slice(5, 5);
            $baselineAverage = $previousFive->avg('response_time');

            foreach ($recentFive as $record) {
                $responseTime = (float) $record->response_time;

                if ($responseTime > 3 && $responseTime > (2 * $baselineAverage)) {
                    $spikeFound = true;
                    $checkedAt = $record->checked_at
                        ? Carbon::parse($record->checked_at)->timezone($timezone)->format('H:i:s').' WIB'
                        : '-';

                    $lines[] = '- **'.$name.'** pada '.$checkedAt
                        .': response time '.number_format($responseTime, 3).' detik'
                        .', dibandingkan rata-rata sebelumnya '
                        .number_format($baselineAverage, 3).' detik'
                        .'. Status: '.$record->status
                        .', HTTP '.($record->http_code ?? '-').'.';
                }
            }
        }

        if (! $spikeFound) {
            $lines[] = 'Tidak ditemukan lonjakan yang memenuhi kriteria.';
        }

        $lines[] = '';
        $lines[] = '## 7. Riwayat Gangguan dan Peringatan';
        $lines[] = '';

        $incidents = $actual->whereIn('status', ['WARNING', 'GANGGUAN']);

        if ($incidents->isEmpty()) {
            $lines[] = 'Tidak ditemukan status WARNING atau GANGGUAN pada tanggal ini.';
        } else {
            foreach ($incidents as $incident) {
                $lines[] = '- '.Carbon::parse($incident->checked_at)->timezone($timezone)->format('H:i:s')
                    .' WIB — '.$incident->website_name
                    .' — '.$incident->status
                    .' — HTTP '.($incident->http_code ?? '-');

                if ($incident->response_time !== null) {
                    $lines[] = '  - Response time: '.number_format((float) $incident->response_time, 3).' detik';
                }

                if (! empty($incident->detail)) {
                    $lines[] = '  - Detail: '.$incident->detail;
                }
            }
        }

        $lines[] = '';
        $lines[] = '> Catatan: laporan ini dibuat berdasarkan data yang tersimpan di database Laravel. Data simulasi bukan bukti kondisi hosting produksi.';
        $lines[] = '';

        $directory = config(
            'hosting.report_directory',
            rtrim(env('HOME', ''), '/').'/Documents/HostingVault/Laporan Harian'
        );

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error('Gagal membuat folder laporan di Obsidian.');

            return self::FAILURE;
        }

        $path = $directory.'/'.$date->format('Y-m-d').'.md';

        if (file_put_contents($path, implode(PHP_EOL, $lines)) === false) {
            $this->error('Gagal menyimpan laporan.');

            return self::FAILURE;
        }

        // Siapkan ringkasan laporan untuk Telegram.
        $summaryLines = [
            '📊 LAPORAN MONITORING HOSTING HARIAN',
            'Tanggal: '.$date->format('d-m-Y'),
            '',
            'Total pemeriksaan aktual: '.$actual->count(),
            'NORMAL: '.$actual->where('status', 'NORMAL')->count(),
            'WARNING: '.$actual->where('status', 'WARNING')->count(),
            'GANGGUAN: '.$actual->where('status', 'GANGGUAN')->count(),
            '',
        ];

        if ($actual->isEmpty()) {
            $summaryLines[] = 'Belum ada data monitoring aktual untuk tanggal ini.';
        } else {
            foreach ($actual->groupBy('website_name') as $name => $records) {
                $times = $records->pluck('response_time')
                    ->filter(fn ($value) => $value !== null);

                $average = $times->isNotEmpty()
                    ? number_format($times->avg(), 3).' detik'
                    : 'Tidak tersedia';

                $summaryLines[] = '• '.$name;
                $summaryLines[] = '  Pemeriksaan: '.$records->count();
                $summaryLines[] = '  NORMAL: '.$records->where('status', 'NORMAL')->count();
                $summaryLines[] = '  WARNING: '.$records->where('status', 'WARNING')->count();
                $summaryLines[] = '  GANGGUAN: '.$records->where('status', 'GANGGUAN')->count();
                $summaryLines[] = '  Rata-rata respons: '.$average;
                $summaryLines[] = '';
            }
        }

        $summaryLines[] = 'Website nonaktif/pengujian: '.$inactiveWebsiteHistory->count();
        $summaryLines[] = 'Data simulasi: '.$simulation->count();
        $summaryLines[] = '';
        $summaryLines[] = 'Catatan: ringkasan berdasarkan data database Laravel. Ini bukan pemeriksaan kesehatan internal server atau bukti kondisi hosting produksi.';

        // Satu notifikasi laporan untuk setiap tanggal.
        $notification = HostingNotification::query()
            ->whereDate('report_date', $date->toDateString())
            ->first();

        if ($notification === null) {
            $notification = HostingNotification::create([
                'report_date' => $date->toDateString(),
                'website_name' => 'Ringkasan Monitoring',
                'website_url' => '-',
                'event_type' => 'daily_report',
                'title' => 'Laporan Monitoring Harian - '.$date->format('Y-m-d'),
                'message' => implode("\n", $summaryLines),
                'checked_at' => now($timezone),
            ]);
        }

        // Service menangani pengiriman, pencegahan duplikat, dan retry.
        app(TelegramNotificationService::class)->send($notification);

        $this->info('Laporan harian berhasil dibuat.');
        $this->line('Tanggal: '.$date->format('Y-m-d'));
        $this->line('Total pemeriksaan: '.$logs->count());
        $this->line('Monitoring aktual: '.$actual->count());
        $this->line('Website nonaktif/pengujian: '.$inactiveWebsiteHistory->count());
        $this->line('Riwayat Hermes: '.$hermesHistory->count());
        $this->line('Data simulasi: '.$simulation->count());
        $this->line('File: '.$path);

        return self::SUCCESS;
    }
}
