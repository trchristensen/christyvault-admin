<?php

namespace App\Notifications;

use App\Models\KanbanCard;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class KanbanCardScanned extends Notification
{
    use Queueable;

    protected $kanbanCard;
    protected $remainingQuantity;

    public function __construct(KanbanCard $kanbanCard, ?float $remainingQuantity = null)
    {
        $this->kanbanCard = $kanbanCard;
        $this->remainingQuantity = $remainingQuantity;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = "Kanban card scanned for {$this->kanbanCard->inventoryItem->name} in {$this->kanbanCard->department}";

        if ($this->remainingQuantity !== null) {
            $message .= "\nRemaining Quantity: {$this->remainingQuantity} {$this->kanbanCard->unit_of_measure}";
        }

        return (new MailMessage)
            ->subject('Kanban Card Scanned')
            ->line($message)
            ->line("Location: {$this->kanbanCard->bin_location}")
            ->line("Bin: {$this->kanbanCard->bin_number}")
            ->action('View Details', url('/admin/kanban-cards/' . $this->kanbanCard->id));
    }

    public function toArray($notifiable): array
    {
        return [
            'kanban_card_id' => $this->kanbanCard->id,
            'inventory_item_id' => $this->kanbanCard->inventory_item_id,
            'inventory_item_name' => $this->kanbanCard->inventoryItem->name,
            'department' => $this->kanbanCard->department,
            'bin_location' => $this->kanbanCard->bin_location,
            'bin_number' => $this->kanbanCard->bin_number,
            // 'scanned_by' => $this->kanbanCard->scannedBy->name,
            'scanned_at' => $this->kanbanCard->last_scanned_at->toDateTimeString(),
        ];
    }
}
