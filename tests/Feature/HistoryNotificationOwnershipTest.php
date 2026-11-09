<?php

namespace Tests\Feature;

use App\Models\HostingNotification;
use App\Models\MonitoringLog;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryNotificationOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function createWebsite(User $user, string $name): Website
    {
        return $user->websites()->create([
            'name' => $name,
            'url' => 'https://' . strtolower(str_replace(' ', '-', $name)) . '.example.com',
            'is_active' => true,
        ]);
    }

    private function createLog(Website $website): MonitoringLog
    {
        return MonitoringLog::create([
            'website_id' => $website->id,
            'website_name' => $website->name,
            'website_url' => $website->url,
            'status' => 'Normal',
            'http_code' => '200',
            'response_time' => 0.500,
            'detail' => 'Pengujian ownership',
            'checked_at' => now(),
        ]);
    }

    private function createNotification(Website $website): HostingNotification
    {
        return HostingNotification::create([
            'website_id' => $website->id,
            'website_name' => $website->name,
            'website_url' => $website->url,
            'event_type' => 'gangguan',
            'title' => 'Notifikasi Uji',
            'message' => 'Pesan pengujian ownership',
            'checked_at' => now(),
        ]);
    }

    public function test_user_only_sees_their_own_monitoring_history(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $myWebsite = $this->createWebsite($user, 'Website Saya');
        $otherWebsite = $this->createWebsite($otherUser, 'Website Orang Lain');

        $myLog = $this->createLog($myWebsite);
        $this->createLog($otherWebsite);

        $this->actingAs($user)
            ->get(route('history'))
            ->assertOk()
            ->assertViewHas('logs', function ($logs) use ($myLog) {
                return $logs->contains('id', $myLog->id)
                    && $logs->count() === 1;
            });
    }

    public function test_user_cannot_delete_another_users_monitoring_history(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $website = $this->createWebsite($otherUser, 'Website Orang Lain');
        $log = $this->createLog($website);

        $this->actingAs($user)
            ->delete(route('history.destroy', $log->id))
            ->assertNotFound();

        $this->assertDatabaseHas('monitoring_logs', [
            'id' => $log->id,
        ]);
    }

    public function test_delete_all_history_only_deletes_current_users_history(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $myWebsite = $this->createWebsite($user, 'Website Saya');
        $otherWebsite = $this->createWebsite($otherUser, 'Website Orang Lain');

        $myLog = $this->createLog($myWebsite);
        $otherLog = $this->createLog($otherWebsite);

        $this->actingAs($user)
            ->delete(route('history.destroyAll'))
            ->assertRedirect('/riwayat');

        $this->assertDatabaseMissing('monitoring_logs', [
            'id' => $myLog->id,
        ]);

        $this->assertDatabaseHas('monitoring_logs', [
            'id' => $otherLog->id,
        ]);
    }

    public function test_user_only_sees_their_own_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $myWebsite = $this->createWebsite($user, 'Website Saya');
        $otherWebsite = $this->createWebsite($otherUser, 'Website Orang Lain');

        $myNotification = $this->createNotification($myWebsite);
        $this->createNotification($otherWebsite);

        $this->actingAs($user)
            ->get(route('notifications'))
            ->assertOk()
            ->assertViewHas('notifications', function ($notifications) use ($myNotification) {
                return $notifications->contains('id', $myNotification->id)
                    && $notifications->count() === 1;
            })
            ->assertViewHas('unreadCount', 1);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $website = $this->createWebsite($otherUser, 'Website Orang Lain');
        $notification = $this->createNotification($website);

        $this->actingAs($user)
            ->patch(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertDatabaseHas('hosting_notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    public function test_user_cannot_delete_another_users_notification(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $website = $this->createWebsite($otherUser, 'Website Orang Lain');
        $notification = $this->createNotification($website);

        $this->actingAs($user)
            ->delete(route('notifications.destroy', $notification->id))
            ->assertNotFound();

        $this->assertDatabaseHas('hosting_notifications', [
            'id' => $notification->id,
        ]);
    }
}
