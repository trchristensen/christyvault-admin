<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Services\Sage100Service;
use Illuminate\Console\Command;

class SyncSageInventory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:sync-sage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync inventory items with Sage 100';

    /**
     * Execute the console command.
     */
    public function handle(Sage100Service $sageService): array
    {
        $items = InventoryItem::whereNotNull('sage_item_code')->get();
        $successCount = 0;
        $errorCount = 0;
        $messages = [];

        foreach ($items as $item) {
            $result = $item->syncWithSage();

            if ($result['status'] === 'success') {
                $successCount++;
            } else {
                $errorCount++;
                $messages[] = "Error syncing {$item->name}: {$result['message']}";
            }

            if ($this->output) {
                $this->info("Synced {$item->name}");
            }
        }

        $summary = "Sync completed: {$successCount} succeeded, {$errorCount} failed";

        if ($this->output) {
            $this->info($summary);
        }

        return [
            'status' => $errorCount === 0 ? 'success' : 'warning',
            'message' => $summary,
            'details' => $messages,
        ];
    }
}
