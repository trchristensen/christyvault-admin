<?php

namespace App\Notifications;

use App\Models\KanbanCard;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class KanbanCardScanned extends Notification
{
    use Queueable;

    protected KanbanCard $kanbanCard;

    public function __construct(KanbanCard $kanbanCard)
    {
        $this->kanbanCard = $kanbanCard;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kanban Card Scanned - Reorder Required')
            ->line('A kanban card has been scanned and requires attention.')
            ->line('Item: ' . $this->kanbanCard->inventoryItem->name)
            ->line('Location: ' . $this->kanbanCard->bin_location)
            ->line('Bin Number: ' . $this->kanbanCard->bin_number)
            // ->line('Scanned by: ' . $this->kanbanCard->scannedBy->name)
            ->action('View Details', url('/operations/kanban-cards/' . $this->kanbanCard->id));
    }

    public function toArray($notifiable): array
    {
        return [
            'kanban_card_id' => $this->kanbanCard->id,
            'inventory_item_id' => $this->kanbanCard->inventory_item_id,
            'inventory_item_name' => $this->kanbanCard->inventoryItem->name,
            'bin_location' => $this->kanbanCard->bin_location,
            'bin_number' => $this->kanbanCard->bin_number,
            'scanned_by' => $this->kanbanCard->scannedBy->name,
            'scanned_at' => $this->kanbanCard->last_scanned_at->toDateTimeString(),
        ];
    }
}
