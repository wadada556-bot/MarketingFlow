<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $user          = User::first();
        $notifications = $user->notifications()->latest()->paginate(20);
        $unreadCount   = $user->unreadNotifications()->count();

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    public function markRead(string $id)
    {
        User::first()->notifications()->where('id', $id)->first()?->markAsRead();

        return back();
    }

    public function markAllRead()
    {
        User::first()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
