<?php

namespace App\Notifications;

use App\Models\KanbanCard;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
            ->subject($this->remainingQuantity ? 'Kanban Card Quantity Update' : 'Kanban Card Scanned')
            ->line($message)
            ->line("Department: {$this->kanbanCard->department}")
            ->line("Bin: {$this->kanbanCard->bin_number}")
            ->line("Location: {$this->kanbanCard->bin_location}")
            ->action('View Details', url('operations/kanban-cards/' . $this->kanbanCard->id));
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
            'remaining_quantity' => $this->remainingQuantity,
            'unit_of_measure' => $this->kanbanCard->unit_of_measure,
            'scanned_by' => $this->kanbanCard->scannedBy?->name ?? 'Unknown'
        ];
    }
}
