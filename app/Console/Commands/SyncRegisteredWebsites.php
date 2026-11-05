<?php

namespace App\Console\Commands;

use App\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncRegisteredWebsites extends Command
{
    protected $signature = 'hosting:sync-websites';

    protected $description = 'Mendaftarkan website dari konfigurasi dan menghubungkan riwayat monitoring lama';

    public function handle(): int
    {
        $websites = config('hosting.websites', []);

        if (empty($websites)) {
            $this->error('Konfigurasi website tidak ditemukan.');

            return self::FAILURE;
        }

        $created = 0;
        $linked = 0;

        DB::transaction(function () use ($websites, &$created, &$linked) {
            foreach ($websites as $name => $data) {
                $url = $data['url'] ?? null;

                if (! is_string($url) || $url === '') {
                    continue;
                }

                $website = Website::firstOrCreate(
                    ['url' => $url],
                    [
                        'name' => $name,
                        'is_active' => true,
                    ]
                );

                if ($website->wasRecentlyCreated) {
                    $created++;
                }

                $count = $website->monitoringLogs()
                    ->whereNull('website_id')
                    ->where('website_name', $name)
                    ->where('website_url', $url)
                    ->update(['website_id' => $website->id]);

                $linked += $count;

                $this->line(
                    "{$name}: website ID {$website->id}, {$count} riwayat dihubungkan."
                );
            }
        });

        $this->info("Website baru: {$created}");
        $this->info("Riwayat yang dihubungkan: {$linked}");
        $this->info('Sinkronisasi selesai.');

        return self::SUCCESS;
    }
}
