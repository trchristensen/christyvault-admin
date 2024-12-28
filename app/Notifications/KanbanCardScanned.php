<?php

namespace App\Notifications;

use App\Models\KanbanCard;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Actions\Action;

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
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Kanban Card Scanned',
            'message' => "Card scanned for {$this->kanbanCard->inventoryItem->name}",
            'inventory_item_name' => $this->kanbanCard->inventoryItem->name,
            'bin_number' => $this->kanbanCard->bin_number,
            'bin_location' => $this->kanbanCard->bin_location,
            'remaining_quantity' => $this->remainingQuantity,
            'unit_of_measure' => $this->kanbanCard->unit_of_measure,
            'scanned_by' => $this->kanbanCard->scannedBy?->name ?? 'Unknown',
            'link' => url("operations/kanban-cards/{$this->kanbanCard->id}"),
            'format' => 'filament'
        ];
    }
}
