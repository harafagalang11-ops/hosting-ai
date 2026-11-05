<?php

namespace App\Services;

use App\Models\HostingNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class TelegramNotificationService
{
    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAY_MINUTES = 5;
    private const LOCK_SECONDS = 60;

    public function send(HostingNotification $notification): bool
    {
        $lock = Cache::lock(
            'telegram-notification-' . $notification->id,
            self::LOCK_SECONDS
        );

        if (!$lock->get()) {
            return false;
        }

        try {
            $notification->refresh();

            if ($notification->telegram_sent_at !== null) {
                return true;
            }

            if ((int) $notification->telegram_attempts >= self::MAX_ATTEMPTS) {
                return false;
            }

            if (
                $notification->telegram_error !== null
                && (
                    $notification->telegram_next_attempt_at === null
                    || $notification->telegram_next_attempt_at->isFuture()
                )
            ) {
                return false;
            }

            $token = config('services.telegram.bot_token');
            $chatId = config('services.telegram.chat_id');

            if (!$token || !$chatId) {
                $notification->update([
                    'telegram_error' => 'Token bot atau chat ID belum dikonfigurasi.',
                    'telegram_next_attempt_at' => null,
                ]);

                return false;
            }

            $attempt = (int) $notification->telegram_attempts + 1;

            if ($attempt > self::MAX_ATTEMPTS) {
                return false;
            }

            $notification->update([
                'telegram_attempts' => $attempt,
                'telegram_next_attempt_at' => null,
            ]);

            $retryable = false;
            $lastError = null;
            $retryAfter = null;

            try {
                $response = Http::asForm()
                    ->timeout(15)
                    ->post(
                        'https' . '://' . 'api.telegram.org' . '/bot' . $token . '/sendMessage',
                        [
                            'chat_id' => $chatId,
                            'text' => $notification->message,
                        ]
                    );

                if ($response->successful() && $response->json('ok')) {
                    $notification->update([
                        'telegram_sent_at' => now(),
                        'telegram_error' => null,
                        'telegram_next_attempt_at' => null,
                    ]);

                    return true;
                }

                $status = $response->status();
                $retryable = $status === 429 || $status >= 500;
                $lastError = 'Telegram API gagal mengirim pesan (HTTP ' . $status . ').';

                if ($status === 429) {
                    $retryAfter = $response->json('parameters.retry_after');
                }
            } catch (Throwable $e) {
                $retryable = true;
                $lastError = 'Pengiriman gagal: ' . $e->getMessage();
            }

            $nextAttemptAt = null;

            if ($retryable && $attempt < self::MAX_ATTEMPTS) {
                $delay = is_numeric($retryAfter)
                    ? max(1, (int) $retryAfter)
                    : self::RETRY_DELAY_MINUTES * 60;

                $nextAttemptAt = now()->addSeconds($delay);
            }

            $notification->update([
                'telegram_error' => $lastError,
                'telegram_next_attempt_at' => $nextAttemptAt,
            ]);

            return false;
        } finally {
            $lock->release();
        }
    }
}
