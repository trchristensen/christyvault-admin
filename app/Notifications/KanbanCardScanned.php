<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;
use App\Models\KanbanCard;

class KanbanCardScanned extends Notification
{
    use Queueable;

    public function __construct(
        protected KanbanCard $kanbanCard
    ) {}

    public function toDatabase($notifiable): array
    {
        return FilamentNotification::make()
            ->title('Kanban Card Scanned')
            ->body("Kanban card scanned for {$this->kanbanCard->inventoryItem->name}. Purchase order created.")
            ->success()
            ->getDatabaseMessage();
    }

    public function via($notifiable): array
    {
        return ['database'];
    }
}
