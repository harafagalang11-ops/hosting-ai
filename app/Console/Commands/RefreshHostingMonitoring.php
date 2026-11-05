<?php

namespace App\Console\Commands;

use App\Models\Website;
use App\Services\HostingStatusNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

#[\Illuminate\Console\Attributes\Signature('hosting:refresh-monitoring')]
#[\Illuminate\Console\Attributes\Description('Memeriksa semua website aktif yang terdaftar')]
class RefreshHostingMonitoring extends Command
{
    public function handle(): int
    {
        $websites = Website::where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($websites->isEmpty()) {
            $this->warn('Tidak ada website aktif untuk diperiksa.');
            return self::SUCCESS;
        }

        foreach ($websites as $website) {
            $name = $website->name;
            $url = $website->url;

            $validationError = $this->validatePublicUrl($url);

            if ($validationError !== null) {
                $this->warn("{$name}: {$validationError}");
                continue;
            }

            $startedAt = microtime(true);
            $checkedAt = now('UTC');
            $httpCode = null;
            $responseTime = null;
            $status = 'GANGGUAN';
            $detail = 'Pemeriksaan gagal.';

            try {
                $response = Http::timeout(20)
                    ->connectTimeout(5)
                    ->withoutRedirecting()
                    ->get($url);

                $responseTime = round(microtime(true) - $startedAt, 3);
                $httpCode = $response->status();

                $status = match (true) {
                    $httpCode >= 200 && $httpCode < 300 => 'NORMAL',
                    $httpCode >= 300 && $httpCode < 400 => 'WARNING',
                    default => 'GANGGUAN',
                };

                $detail = 'Pemeriksaan HTTP langsung oleh Laravel.';

                if ($httpCode >= 300 && $httpCode < 400) {
                    $redirectLocation = $response->header('Location');

                    if (!empty($redirectLocation)) {
                        $detail .= ' Redirect menuju: ' . $redirectLocation;
                    } else {
                        $detail .= ' Header Location tidak tersedia.';
                    }
                }
            } catch (Throwable $e) {
                $responseTime = round(microtime(true) - $startedAt, 3);
                $detail = 'Koneksi gagal: ' . $e->getMessage();

                Log::warning('Hosting refresh gagal', [
                    'website_id' => $website->id,
                    'website' => $name,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }

            $website->monitoringLogs()->create([
                'website_name' => $name,
                'website_url' => $url,
                'status' => $status,
                'http_code' => $httpCode,
                'response_time' => $responseTime,
                'detail' => $detail,
                'checked_at' => $checkedAt,
            ]);

            app(HostingStatusNotificationService::class)
                ->notifyIfChanged(
                    $website->id,
                    $name,
                    $url,
                    $status,
                    $detail,
                    $checkedAt
                );

            $this->line(
                "{$name}: {$status} | HTTP " .
                ($httpCode ?? '-') . " | {$responseTime} detik"
            );
        }

        $this->info('Pemeriksaan website aktif selesai.');
        return self::SUCCESS;
    }

    private function validatePublicUrl(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return 'Format URL tidak valid.';
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(rtrim($parts['host'] ?? '', '.'));
        $port = $parts['port'] ?? null;

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return 'Hanya URL HTTP/HTTPS yang diizinkan.';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'URL dengan informasi login tidak diizinkan.';
        }

        if ($port !== null && !in_array($port, [80, 443], true)) {
            return 'Hanya port 80 dan 443 yang diizinkan.';
        }

        if (
            filter_var($host, FILTER_VALIDATE_IP) ||
            !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) ||
            $host === 'localhost' ||
            str_ends_with($host, '.localhost') ||
            str_ends_with($host, '.local') ||
            str_ends_with($host, '.internal') ||
            str_ends_with($host, '.test')
        ) {
            return 'Host lokal atau alamat IP langsung tidak diizinkan.';
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false || empty($records)) {
            return 'Domain tidak memiliki alamat DNS yang dapat diverifikasi.';
        }

        $addresses = [];

        foreach ($records as $record) {
            if (!empty($record['ip'])) {
                $addresses[] = $record['ip'];
            }

            if (!empty($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        if (empty($addresses)) {
            return 'Alamat DNS domain tidak ditemukan.';
        }

        foreach (array_unique($addresses) as $ip) {
            $publicIp = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

            if ($publicIp === false) {
                return 'Domain mengarah ke alamat IP privat atau khusus.';
            }
        }

        return null;
    }
}
