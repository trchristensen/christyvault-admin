<?php

namespace App\Livewire;

use Livewire\Component;

class NotificationsDropdown extends Component
{
    public function getUnreadCountProperty()
    {
        return auth()->user()->unreadNotifications->count();
    }

    public function markAsRead($notificationId)
    {
        auth()->user()->notifications()
            ->findOrFail($notificationId)
            ->markAsRead();
    }

    public function render()
    {
        return view('livewire.notifications-dropdown', [
            'notifications' => auth()->user()->unreadNotifications()->latest()->take(5)->get()
        ]);
    }
}