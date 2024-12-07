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
        if (!$this->isConfigured()) {
            return [
                'status' => 'error',
                'message' => 'Sage100 service is not configured'
            ];
        }

        if ($this->isTestMode) {
            return [
                'status' => 'success',
                'quantity' => rand(1, 100),
                'message' => 'Test mode: Random quantity generated'
            ];
        }

        return $this->proxyService->post('inventory/level', [
            'item_code' => $itemCode
        ]);
    }

    public function isConfigured(): bool
    {
        if ($this->isTestMode) {
            return true;
        }

        // Check if proxy is healthy (which implicitly checks if it's configured)
        return $this->proxyService->isHealthy();
    }
}
