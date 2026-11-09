<?php

namespace Tests\Feature;

use App\Models\HostingNotification;
use App\Services\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => 'fake-test-token',
            'services.telegram.chat_id' => 'fake-test-chat',
        ]);
    }

    private function createNotification(array $attributes = []): HostingNotification
    {
        return HostingNotification::create(array_merge([
            'website_name' => 'Website Test',
            'website_url' => 'https://example.com',
            'event_type' => 'gangguan',
            'title' => 'Website mengalami gangguan',
            'message' => 'Pesan simulasi pengujian.',
            'checked_at' => now(),
            'telegram_attempts' => 0,
        ], $attributes));
    }

    public function test_successful_telegram_response_marks_notification_as_sent(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $notification = $this->createNotification();

        $result = app(TelegramNotificationService::class)->send($notification);

        $this->assertTrue($result);
        $this->assertNotNull($notification->fresh()->telegram_sent_at);
        $this->assertNull($notification->fresh()->telegram_error);
        $this->assertSame(1, $notification->fresh()->telegram_attempts);

        Http::assertSentCount(1);
    }

    public function test_in_progress_lease_is_saved_before_telegram_request(): void
    {
        $notification = $this->createNotification();

        Http::fake(function ($request, $options) use ($notification) {
            $stored = $notification->fresh();

            $this->assertSame(1, $stored->telegram_attempts);
            $this->assertSame(
                'Pengiriman Telegram belum terkonfirmasi; menunggu pemulihan.',
                $stored->telegram_error
            );
            $this->assertNotNull($stored->telegram_next_attempt_at);
            $this->assertTrue($stored->telegram_next_attempt_at->isFuture());

            return Http::response(['ok' => true], 200);
        });

        $result = app(TelegramNotificationService::class)->send($notification);

        $this->assertTrue($result);
        $this->assertNotNull($notification->fresh()->telegram_sent_at);
        $this->assertNull($notification->fresh()->telegram_error);
        $this->assertNull($notification->fresh()->telegram_next_attempt_at);
    }

    public function test_expired_in_progress_lease_can_be_recovered(): void
    {
        $notification = $this->createNotification([
            'telegram_attempts' => 1,
            'telegram_error' => 'Pengiriman Telegram belum terkonfirmasi; menunggu pemulihan.',
            'telegram_next_attempt_at' => now()->subSecond(),
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $result = app(TelegramNotificationService::class)->send($notification);

        $fresh = $notification->fresh();

        $this->assertTrue($result);
        $this->assertSame(2, $fresh->telegram_attempts);
        $this->assertNotNull($fresh->telegram_sent_at);
        $this->assertNull($fresh->telegram_error);
        $this->assertNull($fresh->telegram_next_attempt_at);
        Http::assertSentCount(1);
    }

    public function test_active_in_progress_lease_prevents_duplicate_send(): void
    {
        $notification = $this->createNotification([
            'telegram_attempts' => 1,
            'telegram_error' => 'Pengiriman Telegram belum terkonfirmasi; menunggu pemulihan.',
            'telegram_next_attempt_at' => now()->addMinute(),
        ]);

        Http::fake();

        $result = app(TelegramNotificationService::class)->send($notification);

        $this->assertFalse($result);
        $this->assertSame(1, $notification->fresh()->telegram_attempts);
        Http::assertNothingSent();
    }

    public function test_http_500_schedules_retry_after_five_minutes(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false], 500),
        ]);

        $now = now();
        $this->travelTo($now);

        $notification = $this->createNotification();

        $result = app(TelegramNotificationService::class)->send($notification);

        $fresh = $notification->fresh();

        $this->assertFalse($result);
        $this->assertSame(1, $fresh->telegram_attempts);
        $this->assertNotNull($fresh->telegram_error);
        $this->assertNotNull($fresh->telegram_next_attempt_at);
        $this->assertSame(
            $now->copy()->addMinutes(5)->timestamp,
            $fresh->telegram_next_attempt_at->timestamp
        );

        Http::assertSentCount(1);
    }

    public function test_http_400_does_not_schedule_retry(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false], 400),
        ]);

        $notification = $this->createNotification();

        $result = app(TelegramNotificationService::class)->send($notification);

        $fresh = $notification->fresh();

        $this->assertFalse($result);
        $this->assertSame(1, $fresh->telegram_attempts);
        $this->assertNotNull($fresh->telegram_error);
        $this->assertNull($fresh->telegram_next_attempt_at);

        Http::assertSentCount(1);
    }

    public function test_notification_with_three_attempts_is_not_sent_again(): void
    {
        Http::fake();

        $notification = $this->createNotification([
            'telegram_attempts' => 3,
            'telegram_error' => 'Batas percobaan tercapai.',
        ]);

        $result = app(TelegramNotificationService::class)->send($notification);

        $this->assertFalse($result);
        $this->assertSame(3, $notification->fresh()->telegram_attempts);

        Http::assertNothingSent();
    }

    public function test_notification_is_not_sent_when_lock_is_already_held(): void
    {
        Http::fake();

        $notification = $this->createNotification();

        $lock = Cache::lock('telegram-notification-'.$notification->id, 60);

        $this->assertTrue($lock->get());

        try {
            $result = app(TelegramNotificationService::class)->send($notification);

            $this->assertFalse($result);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }


    public function test_missing_telegram_configuration_can_be_retried_after_being_fixed(): void
    {
        config([
            'services.telegram.bot_token' => null,
            'services.telegram.chat_id' => null,
        ]);

        $notification = $this->createNotification();

        $firstResult = app(TelegramNotificationService::class)->send($notification);

        $firstState = $notification->fresh();

        $this->assertFalse($firstResult);
        $this->assertSame(0, $firstState->telegram_attempts);
        $this->assertNotNull($firstState->telegram_next_attempt_at);
        $this->assertNotNull($firstState->telegram_error);

        config([
            'services.telegram.bot_token' => 'fake-test-token',
            'services.telegram.chat_id' => 'fake-test-chat',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->travelTo(
            $firstState->telegram_next_attempt_at->copy()->addSecond()
        );

        $secondResult = app(TelegramNotificationService::class)->send(
            $notification->fresh()
        );

        $fresh = $notification->fresh();

        $this->assertTrue($secondResult);
        $this->assertSame(1, $fresh->telegram_attempts);
        $this->assertNotNull($fresh->telegram_sent_at);
        $this->assertNull($fresh->telegram_error);

        Http::assertSentCount(1);
    }

    public function test_connection_exception_does_not_store_sensitive_url_or_token(): void
    {
        Http::fake(function ($request, $options) {
            throw new \Illuminate\Http\Client\ConnectionException(
                'Connection failed: https://api.telegram.org/botfake-test-token/sendMessage'
            );
        });

        $notification = $this->createNotification();

        $result = app(TelegramNotificationService::class)->send($notification);

        $fresh = $notification->fresh();

        $this->assertFalse($result);
        $this->assertSame(
            'Koneksi ke Telegram gagal atau waktu tunggu habis.',
            $fresh->telegram_error
        );
        $this->assertStringNotContainsString(
            'fake-test-token',
            $fresh->telegram_error
        );
        $this->assertNotNull($fresh->telegram_next_attempt_at);
        $this->assertSame(1, $fresh->telegram_attempts);
    }

}
