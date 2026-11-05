<?php

namespace App\Http\Controllers;

use App\Models\HostingNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $notifications = HostingNotification::latest('created_at')->paginate(15);
        $unreadCount = HostingNotification::whereNull('read_at')->count();

        return view('notifications', compact('notifications', 'unreadCount'));
    }

    public function markRead(HostingNotification $notification): RedirectResponse
    {
        $notification->update(['read_at' => now()]);

        return back()->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    public function destroy(HostingNotification $notification): RedirectResponse
    {
        $notification->delete();

        return back()->with('success', 'Notifikasi berhasil dihapus.');
    }
}
