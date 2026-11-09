<?php

namespace App\Http\Controllers;

use App\Models\HostingNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    private function ownedNotifications()
    {
        return HostingNotification::whereHas('website', function ($query) {
            $query->withTrashed()
                ->where('user_id', auth()->id());
        });
    }

    public function index(): View
    {
        $notifications = $this->ownedNotifications()
            ->latest('created_at')
            ->paginate(15);

        $unreadCount = $this->ownedNotifications()
            ->whereNull('read_at')
            ->count();

        return view('notifications', compact('notifications', 'unreadCount'));
    }

    public function markRead($notification): RedirectResponse
    {
        $this->ownedNotifications()
            ->findOrFail($notification)
            ->update(['read_at' => now()]);

        return back()->with('success', 'Notifikasi berhasil ditandai telah dibaca.');
    }

    public function destroy($notification): RedirectResponse
    {
        $this->ownedNotifications()
            ->findOrFail($notification)
            ->delete();

        return back()->with('success', 'Notifikasi berhasil dihapus.');
    }
}
