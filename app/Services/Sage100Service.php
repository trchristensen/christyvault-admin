<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class Sage100Service
{
    protected $proxyService;
    public $isTestMode;

    public function __construct(ProxyService $proxyService)
    {
        $this->proxyService = $proxyService;
        $this->isTestMode = config('app.env') !== 'production';
    }

    public function getInventoryLevel(string $itemCode): array
    {
        if ($this->isTestMode) {
            return [
                'status' => 'success',
                'quantity' => rand(1, 100),
                'message' => 'Test mode: Random quantity generated'
            ];
        }

        try {
            return $this->proxyService->post('inventory/level', [
                'item_code' => $itemCode
            ]);
        } catch (\Exception $e) {
            Log::error('Sage100Service error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'quantity' => null,
                'message' => 'Could not connect to Sage system: ' . $e->getMessage()
            ];
        }
    }

    public function isConfigured(): bool
    {
        if ($this->isTestMode) {
            return true;
        }

        try {
            return $this->proxyService->isHealthy();
        } catch (\Exception $e) {
            Log::error('Sage100Service configuration check failed: ' . $e->getMessage());
            return false;
        }
    }
}
