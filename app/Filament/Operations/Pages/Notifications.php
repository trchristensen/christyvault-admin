<?php

namespace App\Filament\Operations\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

class Notifications extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Notifications';
    protected static ?string $title = 'Notifications';
    protected static ?string $slug = 'notifications';
    
    protected static string $view = 'filament.operations.pages.notifications';

    public function getNotifications(): LengthAwarePaginator
    {
        return auth()->user()->notifications()->paginate(20);
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = auth()->user()->notifications()->findOrFail($notificationId);
        $notification->markAsRead();
        
        FilamentNotification::make()
            ->success()
            ->title('Notification marked as read')
            ->send();
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
        
        FilamentNotification::make()
            ->success()
            ->title('All notifications marked as read')
            ->send();
    }

    public function delete(string $notificationId): void
    {
        $notification = auth()->user()->notifications()->findOrFail($notificationId);
        $notification->delete();
        
        FilamentNotification::make()
            ->success()
            ->title('Notification deleted')
            ->send();
    }

    protected function getViewData(): array 
    {
        return [
            'notifications' => $this->getNotifications(),
        ];
    }
} 