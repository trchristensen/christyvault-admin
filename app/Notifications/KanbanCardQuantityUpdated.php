<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;
use App\Models\KanbanCard;

class KanbanCardQuantityUpdated extends Notification
{
    use Queueable;

    public function __construct(
        protected KanbanCard $kanbanCard,
        protected float $quantity
    ) {}

    public function toDatabase($notifiable): array
    {
        return FilamentNotification::make()
            ->title('Kanban Card Quantity Updated')
            ->body("Quantity updated for {$this->kanbanCard->inventoryItem->name} to {$this->quantity} {$this->kanbanCard->unit_of_measure}")
            ->success()
            ->getDatabaseMessage();
    }

    public function via($notifiable): array
    {
        return ['database'];
    }
} 