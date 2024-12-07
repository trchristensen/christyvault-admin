<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Sage100Service
{
    protected $apiKey;
    protected $baseUrl;
    protected $isTestMode = true;

    public function __construct()
    {
        $this->apiKey = config('sage100.api_key');
        $this->baseUrl = config('sage100.base_url');
    }

    public function isTestMode(): bool
    {
        return $this->isTestMode;
    }

    public function getInventoryLevel(string $itemCode): array
    {
        if ($this->isTestMode) {
            Log::info("Test Mode: Getting inventory level for {$itemCode}");
            return [
                'status' => 'success',
                'quantity' => rand(10, 100), // Random number for testing
                'message' => 'Test mode: Generated mock data'
            ];
        }

        try {
            // TODO: Implement real Sage 100 API call
            throw new \Exception('Sage 100 integration not implemented yet');
        } catch (\Exception $e) {
            Log::error("Sage100 API Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'quantity' => null,
                'message' => 'Could not connect to Sage 100: ' . $e->getMessage()
            ];
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->baseUrl);
    }
}
