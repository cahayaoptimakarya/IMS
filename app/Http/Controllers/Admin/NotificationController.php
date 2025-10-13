<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $filter = $request->query('filter', 'all'); // all|unread|read

        $query = $user->notifications()->latest();
        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filter === 'read') {
            $query->whereNotNull('read_at');
        }

        $notifications = $query->paginate(15)->withQueryString();

        return view('admin.notifications.index', compact('notifications', 'filter'));
    }

    public function markAsRead(Request $request, DatabaseNotification $notification)
    {
        abort_unless($notification->notifiable_id === $request->user()->id && $notification->notifiable_type === get_class($request->user()), 403);
        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }
        return back()->with('status', 'Notifikasi ditandai terbaca.');
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return back()->with('status', 'Semua notifikasi ditandai terbaca.');
    }
}

