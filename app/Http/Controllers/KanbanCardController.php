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

        if (!$kanbanCard->canBeScanned()) {
            return view('kanban-cards.scan', [
                'kanbanCard' => $kanbanCard,
                'error' => 'This card cannot be scanned at this time.'
            ]);
        }

        $kanbanCard->markAsScanned();

        return view('kanban-cards.scan', [
            'kanbanCard' => $kanbanCard,
            'success' => 'Card successfully scanned!'
        ]);
    }

    public function print(KanbanCard $kanbanCard)
    {
        return view('kanban-cards.print', [
            'kanbanCard' => $kanbanCard
        ]);
    }
}