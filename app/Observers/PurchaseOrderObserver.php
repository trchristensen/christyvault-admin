<?php

namespace App\Observers;

use App\Models\PurchaseOrder;
use App\Models\KanbanCard;
use App\Enums\KanbanCardStatus;
use Illuminate\Support\Facades\Log;

class PurchaseOrderObserver
{
    public function updated(PurchaseOrder $purchaseOrder): void
    {
        Log::info('PurchaseOrder updated', [
            'id' => $purchaseOrder->id,
            'status' => $purchaseOrder->status,
            'changed' => $purchaseOrder->wasChanged('status'),
            'item_ids' => $purchaseOrder->items->pluck('inventory_item_id'),
        ]);

        if ($purchaseOrder->wasChanged('status')) {
            $this->updateRelatedKanbanCards($purchaseOrder);
        }
    }

    private function updateRelatedKanbanCards(PurchaseOrder $purchaseOrder): void
    {
        // Get the inventory item IDs from the purchase order items
        $inventoryItemIds = $purchaseOrder->items()
            ->pluck('inventory_item_id')
            ->toArray();

        Log::info('Looking for kanban cards with inventory items', [
            'inventory_item_ids' => $inventoryItemIds
        ]);

        // Find kanban cards directly by inventory_item_id
        $kanbanCards = KanbanCard::whereIn('inventory_item_id', $inventoryItemIds)->get();

        Log::info('Found related kanban cards', [
            'purchase_order_id' => $purchaseOrder->id,
            'kanban_card_count' => $kanbanCards->count(),
            'kanban_card_ids' => $kanbanCards->pluck('id'),
            'kanban_cards' => $kanbanCards->toArray(),
        ]);

        // Map PO status to Kanban card status
        $newStatus = match ($purchaseOrder->status) {
            'draft' => KanbanCardStatus::PENDING_ORDER->value,      // Still in draft, but order process started
            'submitted' => KanbanCardStatus::ORDERED->value,        // Order has been submitted to supplier
            'awaiting_invoice' => KanbanCardStatus::ORDERED->value, // Still ordered, waiting for invoice
            'received' => KanbanCardStatus::ACTIVE->value,          // Items received, card can be used again
            'completed' => KanbanCardStatus::ACTIVE->value,         // Order complete, card can be used again
            'cancelled' => KanbanCardStatus::ACTIVE->value,         // Order cancelled, card can be used again
            default => null
        };

        if ($newStatus) {
            foreach ($kanbanCards as $card) {
                Log::info('Updating kanban card status', [
                    'card_id' => $card->id,
                    'old_status' => $card->status,
                    'new_status' => $newStatus,
                    'po_status' => $purchaseOrder->status
                ]);

                $card->status = $newStatus;
                $card->save();
            }
        }
    }
}
