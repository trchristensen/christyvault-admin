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
        if ($sageService->isTestMode) {
            return [
                'status' => 'warning',
                'message' => 'Running in test mode - no actual sync performed',
                'details' => ['Test mode is enabled. Configure proxy settings to connect to real Sage 100.']
            ];
        }

        try {
            if (!$sageService->isConfigured()) {
                return [
                    'status' => 'error',
                    'message' => 'Sage 100 service is not properly configured',
                    'details' => ['Check your proxy settings and connection to Sage 100']
                ];
            }

            $items = InventoryItem::whereNotNull('sage_item_code')->get();
            $successCount = 0;
            $errorCount = 0;
            $messages = [];

            foreach ($items as $item) {
                try {
                    $result = $item->syncWithSage();
                    if ($result['status'] === 'success') {
                        $successCount++;
                    } else {
                        $errorCount++;
                        $messages[] = "Failed to sync {$item->name}: {$result['message']}";
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $messages[] = "Error syncing {$item->name}: {$e->getMessage()}";
                }
            }

            return [
                'status' => $errorCount === 0 ? 'success' : 'warning',
                'message' => "Sync completed: {$successCount} succeeded, {$errorCount} failed",
                'details' => $messages
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Sync failed: ' . $e->getMessage(),
                'details' => [$e->getMessage()]
            ];
        }
    }
}
