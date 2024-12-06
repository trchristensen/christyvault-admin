<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Filament\Facades\Filament;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Filament::auth()->user()->notifications()->paginate(20);
        
        return view('notifications.index', compact('notifications'));
    }

    public function markAsRead(Request $request)
    {
        Filament::auth()->user()->unreadNotifications->markAsRead();
        
        return back()->with('success', 'Notifications marked as read');
    }
} 