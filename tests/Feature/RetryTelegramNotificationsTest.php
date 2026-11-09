<?php

namespace Tests\Feature;

use App\Models\HostingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RetryTelegramNotificationsTest extends TestCase
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
            'telegram_attempts' => 1,
            'telegram_error' => 'Pengiriman Telegram belum terkonfirmasi; menunggu pemulihan.',
            'telegram_next_attempt_at' => now()->subMinute(),
        ], $attributes));
    }

    public function test_command_retries_notification_with_expired_lease(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $notification = $this->createNotification();

        $this->artisan('hosting:retry-telegram')
            ->expectsOutput('Pemrosesan retry Telegram selesai. Kandidat retry diperiksa: 1')
            ->assertExitCode(0);

        $fresh = $notification->fresh();

        $this->assertSame(2, $fresh->telegram_attempts);
        $this->assertNotNull($fresh->telegram_sent_at);
        $this->assertNull($fresh->telegram_error);
        $this->assertNull($fresh->telegram_next_attempt_at);

        Http::assertSentCount(1);
    }

    public function test_command_skips_notifications_not_yet_due_and_at_attempt_limit(): void
    {
        Http::fake();

        $futureNotification = $this->createNotification([
            'telegram_next_attempt_at' => now()->addMinute(),
        ]);

        $maxAttemptNotification = $this->createNotification([
            'telegram_attempts' => 3,
            'telegram_next_attempt_at' => now()->subMinute(),
        ]);

        $this->artisan('hosting:retry-telegram')
            ->expectsOutput('Pemrosesan retry Telegram selesai. Kandidat retry diperiksa: 0')
            ->assertExitCode(0);

        $this->assertSame(1, $futureNotification->fresh()->telegram_attempts);
        $this->assertSame(3, $maxAttemptNotification->fresh()->telegram_attempts);

        Http::assertNothingSent();
    }
}
