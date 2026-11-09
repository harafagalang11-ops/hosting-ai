<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\MigrationController;
use App\Http\Controllers\MigrationEndpointController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WebsiteController;
use App\Models\MonitoringLog;
use App\Models\Website;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

if (! function_exists('getHostingMonitoring')) {
    function getHostingMonitoring()
    {
        $websites = config('hosting.websites', []);
        $statusPath = config('hosting.status_path');
        $monitoring = [];

        foreach ($websites as $name => $website) {
            $file = $statusPath.'/'.$website['file'];
            $status = file_exists($file)
                ? strtoupper(trim(file_get_contents($file)))
                : 'UNKNOWN';

            $monitoring[] = [
                'name' => $name,
                'url' => $website['url'],
                'status' => $status,
            ];
        }

        return $monitoring;
    }
}

// Route publik
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

// Route yang memerlukan login
Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', function () {
        $websites = auth()->user()->websites()->where('is_active', true)
            ->orderBy('name')
            ->get();

        $monitoring = [];
        $latestLogs = [];

        foreach ($websites as $website) {
            $latest = MonitoringLog::where('website_id', $website->id)
                ->orderByDesc('checked_at')
                ->first();

            $status = $latest?->status ?? 'UNKNOWN';

            $monitoring[] = [
                'name' => $website->name,
                'url' => $website->url,
                'status' => $status,
            ];

            $latestLogs[$website->id] = $latest;
        }

        $total = count($monitoring);
        $normal = count(array_filter($monitoring, fn ($site) => $site['status'] === 'NORMAL'));
        $warning = count(array_filter($monitoring, fn ($site) => $site['status'] === 'WARNING'));
        $gangguan = count(array_filter($monitoring, fn ($site) => $site['status'] === 'GANGGUAN'));

        $chartRange = request()->query('range', '24h');
        $rangeHours = [
            '24h' => 24,
            '7d' => 24 * 7,
            '30d' => 24 * 30,
        ];

        if (! array_key_exists($chartRange, $rangeHours)) {
            $chartRange = '24h';
        }

        $startTime = now('Asia/Jakarta')
            ->subHours($rangeHours[$chartRange])
            ->utc();

        $chartData = [];

        foreach ($websites as $website) {
            $chartData[$website->name] = MonitoringLog::where('website_id', $website->id)
                ->whereNotNull('response_time')
                ->where('checked_at', '>=', $startTime)
                ->orderBy('checked_at')
                ->get()
                ->map(fn ($log) => [
                    'label' => $log->checked_at
                        ? $log->checked_at->timezone('Asia/Jakarta')->format('d/m H:i')
                        : '-',
                    'response_time' => (float) $log->response_time,
                ])
                ->values();
        }

        // Skor kesehatan dihitung dari monitoring 24 jam terakhir.
        $healthScores = [];

        foreach ($websites as $website) {
            $latest = $latestLogs[$website->id];

            $incidentCount = MonitoringLog::where('website_id', $website->id)
                ->whereIn('status', ['WARNING', 'GANGGUAN'])
                ->where('checked_at', '>=', now('Asia/Jakarta')->subHours(24)->utc())
                ->count();

            if (! $latest) {
                $healthScores[$website->name] = [
                    'score' => null,
                    'label' => 'Data Belum Cukup',
                    'incident_count' => $incidentCount,
                ];

                continue;
            }

            $statusScore = match ($latest->status) {
                'NORMAL' => 100,
                'WARNING' => 65,
                'GANGGUAN' => 20,
                default => 0,
            };

            $time = $latest->response_time;
            $responseScore = $time === null ? 0 : (
                $time < 1 ? 100 : ($time <= 3 ? 75 : 40)
            );

            $incidentScore = match (true) {
                $incidentCount === 0 => 100,
                $incidentCount === 1 => 80,
                $incidentCount === 2 => 60,
                default => 30,
            };

            $score = (int) round(
                ($statusScore * 0.50) +
                ($responseScore * 0.30) +
                ($incidentScore * 0.20)
            );

            $label = $score >= 85
                ? 'Sehat'
                : ($score >= 65 ? 'Perlu Perhatian' : 'Kritis');

            $healthScores[$website->name] = [
                'score' => $score,
                'label' => $label,
                'incident_count' => $incidentCount,
                'status_score' => $statusScore,
                'response_score' => $responseScore,
                'incident_score' => $incidentScore,
            ];
        }

        return view('dashboard', compact(
            'monitoring',
            'total',
            'normal',
            'warning',
            'gangguan',
            'chartData',
            'chartRange',
            'healthScores'
        ));
    })->name('dashboard');

    Route::get('/monitoring', function () {
        $websites = auth()->user()->websites()->where('is_active', true)
            ->orderBy('name')
            ->get();

        $monitoring = [];
        $checkResults = [];

        foreach ($websites as $website) {
            $latest = $website->monitoringLogs()
                ->orderByDesc('checked_at')
                ->first();

            $status = $latest?->status ?? 'UNKNOWN';

            $monitoring[] = [
                'name' => $website->name,
                'url' => $website->url,
                'status' => $status,
            ];

            $checkResults[] = [
                'name' => $website->name,
                'url' => $website->url,
                'status' => $status,
                'http_code' => $latest?->http_code,
                'response_time' => $latest?->response_time,
                'checked_at' => $latest?->checked_at
                    ? $latest->checked_at->timezone('Asia/Jakarta')->format('d-m-Y H:i:s')
                    : '-',
                'detail' => $latest?->detail ?? 'Belum ada data monitoring.',
            ];
        }

        return view('monitoring', compact('monitoring', 'checkResults'));
    })->name('monitoring');

    Route::post('/monitoring/check', function () {
        $websites = auth()->user()->websites()->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($websites->isEmpty()) {
            return redirect()->route('monitoring')
                ->with('success', 'Tidak ada website aktif milik akun Anda untuk diperiksa.');
        }

        $exitCode = Artisan::call('hosting:refresh-monitoring', [
            '--website-id' => $websites->modelKeys(),
        ]);

        if ($exitCode !== 0) {
            return redirect()->route('monitoring')
                ->with('error', 'Pemeriksaan monitoring gagal dijalankan.');
        }

        $websites = auth()->user()->websites()->where('is_active', true)
            ->orderBy('name')
            ->get();

        $checkResults = [];

        foreach ($websites as $website) {
            $latest = $website->monitoringLogs()
                ->orderByDesc('checked_at')
                ->first();

            $checkResults[] = [
                'name' => $website->name,
                'url' => $website->url,
                'status' => $latest?->status ?? 'UNKNOWN',
                'http_code' => $latest?->http_code,
                'response_time' => $latest?->response_time,
                'checked_at' => $latest?->checked_at
                    ? $latest->checked_at->timezone('Asia/Jakarta')->format('d-m-Y H:i:s')
                    : '-',
                'detail' => $latest?->detail ?? 'Belum ada data monitoring.',
            ];
        }

        return redirect()->route('monitoring')
            ->with('check_results', $checkResults)
            ->with('success', 'Pemeriksaan website aktif selesai dan hasilnya tersimpan.');
    })->name('monitoring.check');

    Route::get('/migrasi', [MigrationController::class, 'index'])->name('migration.index');
    Route::post('/migrasi/jalankan', [MigrationController::class, 'run'])
        ->name('migration.run');

    Route::get('/kelola-hosting', [MigrationEndpointController::class, 'index'])
        ->name('migration-endpoints.index');
    Route::post('/kelola-hosting', [MigrationEndpointController::class, 'store'])
        ->name('migration-endpoints.store');
    Route::get('/kelola-hosting/{id}/files', [MigrationEndpointController::class, 'browseFiles'])
        ->whereNumber('id')->name('migration-endpoints.files');
    Route::get('/kelola-hosting/{id}/inventory', [MigrationEndpointController::class, 'inventory'])
        ->whereNumber('id')->name('migration-endpoints.inventory');
    Route::get('/kelola-hosting/{id}/edit', [MigrationEndpointController::class, 'edit'])
        ->whereNumber('id')->name('migration-endpoints.edit');
    Route::put('/kelola-hosting/{id}', [MigrationEndpointController::class, 'update'])
        ->whereNumber('id')->name('migration-endpoints.update');
    Route::post('/kelola-hosting/{id}/test-connection', [MigrationEndpointController::class, 'testConnection'])
        ->whereNumber('id')->name('migration-endpoints.test-connection');
    Route::delete('/kelola-hosting/{id}', [MigrationEndpointController::class, 'destroy'])
        ->whereNumber('id')->name('migration-endpoints.destroy');
    Route::get('/chatbot', [ChatbotController::class, 'index'])
        ->name('chatbot');

    Route::post('/chatbot/send', [ChatbotController::class, 'send'])
        ->name('chatbot.send');
    Route::delete('/chatbot/clear', [ChatbotController::class, 'clear'])
        ->name('chatbot.clear');

    Route::get('/riwayat', function () {
        $logs = MonitoringLog::whereHas('website', function ($query) {
            $query->withTrashed()->where('user_id', auth()->id());
        })->latest('checked_at')->paginate(20);

        return view('history', compact('logs'));
    })->name('history');

    Route::delete('/riwayat/{id}', [HistoryController::class, 'destroy'])
        ->whereNumber('id')
        ->name('history.destroy');

    Route::delete('/riwayat/hapus-semua', [HistoryController::class, 'destroyAll'])
        ->name('history.destroyAll');

    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])
        ->name('notifications.destroy');

    Route::get('/profile', [ProfileController::class, 'index'])
        ->name('profile');

    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::get('/settings', [SettingsController::class, 'index'])
        ->name('settings');

    Route::put('/settings/theme', [SettingsController::class, 'updateTheme'])
        ->name('settings.theme');
});

Route::middleware('auth')->group(function () {
    Route::get('/websites', [WebsiteController::class, 'index'])->name('websites.index');
    Route::get('/websites/{website}/edit', [WebsiteController::class, 'edit'])->name('websites.edit');
    Route::post('/websites', [WebsiteController::class, 'store'])->name('websites.store');
    Route::put('/websites/{website}', [WebsiteController::class, 'update'])->name('websites.update');
    Route::post('/websites/{website}/toggle', [WebsiteController::class, 'toggle'])->name('websites.toggle');
    Route::delete('/websites/{website}', [WebsiteController::class, 'destroy'])->name('websites.destroy');
});
