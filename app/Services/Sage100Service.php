<?php

namespace App\Services;

class Sage100Service
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct(string $apiKey, string $baseUrl)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
    }

    public function getInventoryLevel(string $itemCode): array
    {
        // TODO: Implement actual Sage 100 API call
        // This is where you'd make the HTTP request to Sage 100

        // Example:
        // return Http::withHeaders([
        //     'Authorization' => 'Bearer ' . $this->apiKey,
        // ])->get("{$this->baseUrl}/inventory/{$itemCode}")
        //     ->json('quantity');

        return [];
    }

    public function updateInventoryLevel(string $itemCode, float $quantity)
    {
        // TODO: Implement actual Sage 100 API call
        // This is where you'd make the HTTP request to Sage 100

        // Example:
        // return Http::withHeaders([
        //     'Authorization' => 'Bearer ' . $this->apiKey,
        // ])->put("{$this->baseUrl}/inventory/{$itemCode}", [
        //     'quantity' => $quantity
        // ]);
    }
}
