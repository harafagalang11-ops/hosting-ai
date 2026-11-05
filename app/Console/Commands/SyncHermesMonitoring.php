<?php

namespace App\Console\Commands;

use App\Models\MonitoringLog;
use App\Models\Website;
use App\Services\HostingStatusNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

#[\Illuminate\Console\Attributes\Signature('app:sync-hermes-monitoring')]
#[\Illuminate\Console\Attributes\Description('Sinkronisasi hasil monitoring Hermes ke database Laravel')]
class SyncHermesMonitoring extends Command
{
    public function handle(): int
    {
        $reportPath = env('HOME') . '/Documents/HostingVault/Cek Hosting Terbaru.md';

        if (!file_exists($reportPath)) {
            $this->error('File laporan Hermes tidak ditemukan: ' . $reportPath);
            return self::FAILURE;
        }

        $content = file_get_contents($reportPath);

        if (!preg_match('/Waktu:\s*(\d{2}-\d{2}-\d{4}\s+\d{2}:\d{2}:\d{2})/', $content, $timeMatch)) {
            $this->error('Waktu pemeriksaan tidak ditemukan dalam laporan.');
            return self::FAILURE;
        }

        $checkedAt = Carbon::createFromFormat(
            'd-m-Y H:i:s',
            $timeMatch[1],
            'Asia/Jakarta'
        )->utc();

        preg_match_all(
            '/\*\*(NORMAL|WARNING|GANGGUAN)\s*:\*\*\s*(https?:\/\/\S+)\s*\|\s*HTTP\s+(\d{3})\s*\|\s*([\d.]+)s/',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        if (empty($matches)) {
            $this->error('Tidak ada data website yang dapat dibaca dari laporan.');
            return self::FAILURE;
        }

        $saved = 0;
        $skipped = 0;
        $notifications = 0;

        foreach ($matches as $match) {
            $status = $match[1];
            $url = rtrim($match[2], '/');
            $httpCode = (int) $match[3];
            $responseTime = (float) $match[4];

            $name = match (parse_url($url, PHP_URL_HOST)) {
                'google.com', 'www.google.com' => 'Google',
                'github.com', 'www.github.com' => 'GitHub',
                default => parse_url($url, PHP_URL_HOST) ?: $url,
            };

            $website = Website::query()
                ->where('is_active', true)
                ->get()
                ->first(fn (Website $item) => rtrim($item->url, '/') === $url);

            $websiteId = $website?->id;

            $existsQuery = MonitoringLog::where('checked_at', $checkedAt);

            if ($websiteId !== null) {
                $existsQuery->where('website_id', $websiteId);
            } else {
                $existsQuery->whereNull('website_id')
                    ->where('website_name', $name);
            }

            if ($existsQuery->exists()) {
                $skipped++;
                continue;
            }

            MonitoringLog::create([
                'website_id' => $websiteId,
                'website_name' => $name,
                'website_url' => $url,
                'status' => $status,
                'http_code' => $httpCode,
                'response_time' => $responseTime,
                'detail' => 'Sumber: Hermes Agent',
                'checked_at' => $checkedAt,
            ]);

            $saved++;

            if ($websiteId !== null) {
                $notification = app(HostingStatusNotificationService::class)
                    ->notifyIfChanged(
                        $websiteId,
                        $name,
                        $url,
                        $status,
                        "HTTP {$httpCode} | {$responseTime}s",
                        $checkedAt
                    );

                if ($notification) {
                    $notifications++;
                }
            }
        }

        $this->info(
            "Sinkronisasi selesai. Data baru: {$saved}; duplikat: {$skipped}; notifikasi dibuat: {$notifications}."
        );

        return self::SUCCESS;
    }
}
