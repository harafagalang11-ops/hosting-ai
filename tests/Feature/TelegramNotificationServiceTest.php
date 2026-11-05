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

        $lock = Cache::lock('telegram-notification-' . $notification->id, 60);

        $this->assertTrue($lock->get());

        try {
            $result = app(TelegramNotificationService::class)->send($notification);

            $this->assertFalse($result);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }
}
