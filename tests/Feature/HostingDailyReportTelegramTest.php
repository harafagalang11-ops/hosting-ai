<?php

namespace Tests\Feature;

use App\Models\HostingNotification;
use App\Models\MonitoringLog;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HostingDailyReportTelegramTest extends TestCase
{
    use RefreshDatabase;

    protected string $reportDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => 'fake-test-token',
            'services.telegram.chat_id' => 'fake-test-chat',
        ]);

        $this->reportDirectory = storage_path(
            'framework/testing/hosting-reports-'.uniqid()
        );

        config([
            'hosting.report_directory' => $this->reportDirectory,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->reportDirectory);

        parent::tearDown();
    }

    private function createMonitoringData(): void
    {
        $website = Website::create([
            'name' => 'Website Test',
            'url' => 'https://example.com',
            'is_active' => true,
        ]);

        MonitoringLog::create([
            'website_id' => $website->id,
            'website_name' => $website->name,
            'website_url' => $website->url,
            'status' => 'NORMAL',
            'http_code' => '200',
            'response_time' => 0.500,
            'detail' => 'Pengujian otomatis.',
            'checked_at' => now('Asia/Jakarta')
                ->setDate(2026, 11, 5)
                ->utc(),
        ]);
    }

    public function test_daily_report_is_sent_to_telegram_only_once_per_date(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 123],
            ], 200),
        ]);

        $this->createMonitoringData();

        $firstRun = Artisan::call('hosting:daily-report', [
            'date' => '2026-11-05',
        ]);

        $secondRun = Artisan::call('hosting:daily-report', [
            'date' => '2026-11-05',
        ]);

        $this->assertSame(0, $firstRun);
        $this->assertSame(0, $secondRun);

        $this->assertFileExists(
            $this->reportDirectory.'/2026-11-05.md'
        );

        $this->assertDatabaseCount('hosting_notifications', 1);

        $notification = HostingNotification::first();

        $this->assertSame(
            '2026-11-05',
            $notification->report_date->format('Y-m-d')
        );

        $this->assertSame('daily_report', $notification->event_type);
        $this->assertNotNull($notification->telegram_sent_at);
        $this->assertStringContainsString(
            'Total pemeriksaan aktual: 1',
            $notification->message
        );

        Http::assertSentCount(1);
    }

    public function test_failed_daily_report_telegram_delivery_is_scheduled_for_retry(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => false,
            ], 500),
        ]);

        $this->createMonitoringData();

        $exitCode = Artisan::call('hosting:daily-report', [
            'date' => '2026-11-05',
        ]);

        $this->assertSame(0, $exitCode);

        $notification = HostingNotification::first();

        $this->assertNotNull($notification);
        $this->assertNotNull($notification->telegram_error);
        $this->assertSame(1, $notification->telegram_attempts);
        $this->assertNotNull($notification->telegram_next_attempt_at);
        $this->assertNull($notification->telegram_sent_at);

        Http::assertSentCount(1);
    }
}
