<?php

namespace App\Services;

use App\Models\HostingNotification;
use App\Models\MonitoringLog;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class HostingStatusNotificationService
{
    public function notifyIfChanged(
        int $websiteId,
        string $name,
        string $url,
        string $newStatus,
        string $detail,
        Carbon $checkedAt
    ): ?HostingNotification {
        try {
            $notification = DB::transaction(function () use (
                $websiteId, $name, $url, $newStatus, $detail, $checkedAt
            ) {
                $previous = MonitoringLog::where('website_id', $websiteId)
                    ->where('checked_at', '<', $checkedAt)
                    ->orderByDesc('checked_at')
                    ->first();

                $newStatus = strtoupper($newStatus);

                if (!$previous || strtoupper($previous->status) === $newStatus) {
                    return null;
                }

                $event = match ($newStatus) {
                    'GANGGUAN' => 'gangguan',
                    'WARNING' => 'warning',
                    'NORMAL' => 'pulih',
                    default => null,
                };

                if (!$event) {
                    return null;
                }

                $title = match ($event) {
                    'gangguan' => "Gangguan terdeteksi: {$name}",
                    'warning' => "Peringatan hosting: {$name}",
                    'pulih' => "Website pulih: {$name}",
                };

                $heading = match ($event) {
                    'gangguan' => '🚨 GANGGUAN HOSTING',
                    'warning' => '⚠️ PERINGATAN HOSTING',
                    'pulih' => '✅ WEBSITE PULIH',
                };

                $message = $heading
                    . "\nWebsite: {$name}"
                    . "\nURL: {$url}"
                    . "\nStatus: {$newStatus}"
                    . "\nDetail: {$detail}"
                    . "\nWaktu: " . $checkedAt->copy()
                        ->timezone('Asia/Jakarta')
                        ->format('d-m-Y H:i:s') . ' WIB';

                return HostingNotification::create([
                    'website_id' => $websiteId,
                    'website_name' => $name,
                    'website_url' => $url,
                    'event_type' => $event,
                    'title' => $title,
                    'message' => $message,
                    'checked_at' => $checkedAt,
                ]);
            });
        } catch (QueryException $e) {
            $message = $e->getMessage();

            if (
                str_contains($message, 'UNIQUE constraint failed')
                && str_contains($message, 'hosting_notifications.website_id')
                && str_contains($message, 'hosting_notifications.checked_at')
            ) {
                return null;
            }

            throw $e;
        }

        if ($notification) {
            app(TelegramNotificationService::class)->send($notification);
        }

        return $notification;
    }
}
