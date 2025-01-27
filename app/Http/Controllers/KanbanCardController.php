<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\KanbanCard;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

class KanbanCardController extends Controller
{
    public function downloadQrCode(KanbanCard $kanbanCard)
    {
        $qrCode = $kanbanCard->generateQrCode();
        $filename = "kanban-card-{$kanbanCard->id}.svg";

        return response($qrCode)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function scan(Request $request, $id)
    {
        $kanbanCard = KanbanCard::findOrFail($id);

        // Verify both inventory_item_id and scan_token
        if ($request->inventory_item_id != $kanbanCard->inventory_item_id || 
            $request->token !== $kanbanCard->scan_token) {
            abort(400, 'Invalid QR code');
        }

        // Generate new token after successful scan
        $kanbanCard->refreshScanToken();

        // Handle POST request (quantity update)
        if ($request->isMethod('post')) {
            $request->validate([
                'remaining_quantity' => 'nullable|numeric|min:0'
            ]);

            // Update the quantity if provided
            if ($request->has('remaining_quantity')) {
                $kanbanCard->updateQuantity($request->remaining_quantity);
                return view('kanban-cards.scan', [
                    'kanbanCard' => $kanbanCard,
                    'success' => 'Quantity updated successfully!'
                ]);
            }
        }

        // Handle GET request (initial scan)
        if (!$kanbanCard->canBeScanned()) {
            return view('kanban-cards.scan', [
                'kanbanCard' => $kanbanCard,
                'error' => 'This card cannot be scanned at this time.'
            ]);
        }

        // Send notification on initial scan
        $kanbanCard->markAsScanned();

        // Show the form for entering quantity
        return view('kanban-cards.scan', [
            'kanbanCard' => $kanbanCard,
            'showQuantityForm' => true,
            'success' => 'Card scanned successfully! Enter remaining quantity if needed.'
        ]);
    }

    public function print(KanbanCard $kanbanCard)
    {
        // include inventory item for the kanban card
        $kanbanCard->load('inventoryItem');

        return view('kanban-cards.print', [
            'kanbanCard' => $kanbanCard
        ]);
    }
}
