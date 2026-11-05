<?php

namespace Tests\Feature;

use App\Models\HostingNotification;
use App\Models\MonitoringLog;
use App\Models\Website;
use App\Services\HostingStatusNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HostingStatusNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => 'fake-test-token',
            'services.telegram.chat_id' => 'fake-test-chat',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
    }

    private function createWebsite(): Website
    {
        return Website::create([
            'name' => 'Website Test',
            'url' => 'https://example.com',
            'is_active' => true,
        ]);
    }

    private function createPreviousLog(
        Website $website,
        string $status
    ): void {
        MonitoringLog::create([
            'website_id' => $website->id,
            'website_name' => $website->name,
            'website_url' => $website->url,
            'status' => $status,
            'http_code' => 200,
            'response_time' => 0.500,
            'detail' => 'Data monitoring sebelumnya.',
            'checked_at' => now()->subMinutes(10),
        ]);
    }

    public function test_status_change_to_gangguan_creates_notification(): void
    {
        $website = $this->createWebsite();
        $this->createPreviousLog($website, 'NORMAL');

        $notification = app(HostingStatusNotificationService::class)
            ->notifyIfChanged(
                $website->id,
                $website->name,
                $website->url,
                'GANGGUAN',
                'HTTP 500',
                now()
            );

        $this->assertInstanceOf(HostingNotification::class, $notification);
        $this->assertSame('gangguan', $notification->event_type);
        $this->assertSame(1, HostingNotification::count());
        Http::assertSentCount(1);
    }

    public function test_unchanged_status_does_not_create_notification(): void
    {
        $website = $this->createWebsite();
        $this->createPreviousLog($website, 'NORMAL');

        $notification = app(HostingStatusNotificationService::class)
            ->notifyIfChanged(
                $website->id,
                $website->name,
                $website->url,
                'NORMAL',
                'HTTP 200',
                now()
            );

        $this->assertNull($notification);
        $this->assertSame(0, HostingNotification::count());
        Http::assertNothingSent();
    }

    public function test_status_change_to_normal_creates_recovery_notification(): void
    {
        $website = $this->createWebsite();
        $this->createPreviousLog($website, 'GANGGUAN');

        $notification = app(HostingStatusNotificationService::class)
            ->notifyIfChanged(
                $website->id,
                $website->name,
                $website->url,
                'NORMAL',
                'HTTP 200',
                now()
            );

        $this->assertInstanceOf(HostingNotification::class, $notification);
        $this->assertSame('pulih', $notification->event_type);
        $this->assertSame(1, HostingNotification::count());
        Http::assertSentCount(1);
    }

    public function test_duplicate_notification_for_same_website_and_time_is_ignored(): void
    {
        $website = $this->createWebsite();
        $this->createPreviousLog($website, 'NORMAL');

        $checkedAt = now();

        $service = app(HostingStatusNotificationService::class);

        $first = $service->notifyIfChanged(
            $website->id,
            $website->name,
            $website->url,
            'GANGGUAN',
            'HTTP 500',
            $checkedAt
        );

        $second = $service->notifyIfChanged(
            $website->id,
            $website->name,
            $website->url,
            'GANGGUAN',
            'HTTP 500',
            $checkedAt
        );

        $this->assertInstanceOf(HostingNotification::class, $first);
        $this->assertNull($second);
        $this->assertSame(1, HostingNotification::count());
        Http::assertSentCount(1);
    }
}
