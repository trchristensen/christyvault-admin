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

        // Verify the inventory_item_id matches (security check)
        if ($request->inventory_item_id != $kanbanCard->inventory_item_id) {
            abort(400, 'Invalid QR code');
        }

        // Handle POST request (quantity update)
        if ($request->isMethod('post')) {
            if (!$kanbanCard->canBeScanned()) {
                return view('kanban-cards.scan', [
                    'kanbanCard' => $kanbanCard,
                    'error' => 'This card cannot be scanned at this time.'
                ]);
            }

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
        return view('kanban-cards.print', [
            'kanbanCard' => $kanbanCard
        ]);
    }
}
