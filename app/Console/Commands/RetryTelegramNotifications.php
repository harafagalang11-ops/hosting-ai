<?php

namespace App\Console\Commands;

use App\Models\HostingNotification;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;

#[\Illuminate\Console\Attributes\Signature('hosting:retry-telegram')]
#[\Illuminate\Console\Attributes\Description('Mencoba kembali pengiriman notifikasi Telegram yang gagal sementara')]
class RetryTelegramNotifications extends Command
{
    public function handle(): int
    {
        $checked = 0;

        HostingNotification::query()
            ->whereNull('telegram_sent_at')
            ->whereNotNull('telegram_error')
            ->whereNotNull('telegram_next_attempt_at')
            ->where('telegram_next_attempt_at', '<=', now())
            ->where('telegram_attempts', '<', 3)
            ->orderBy('id')
            ->chunkById(100, function ($notifications) use (&$checked) {
                foreach ($notifications as $notification) {
                    if ($notification->telegram_sent_at !== null) {
                        continue;
                    }

                    app(TelegramNotificationService::class)
                        ->send($notification);

                    $checked++;
                }
            });

        $this->info("Pemrosesan retry Telegram selesai. Kandidat retry diperiksa: {$checked}");

        return self::SUCCESS;
    }
}
