<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProxyService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('proxy.base_url');
        $this->apiKey = config('proxy.api_key');
    }

    public function isHealthy(): bool
    {
        try {
            $response = Http::get("{$this->baseUrl}/api/health");
            $data = $response->json();

            return $data['status'] === 'healthy';
        } catch (\Exception $e) {
            Log::error("Proxy health check failed: " . $e->getMessage());
            return false;
        }
    }

    public function post(string $endpoint, array $data): array
    {
        try {
            $response = Http::timeout(30)
                ->post("{$this->baseUrl}/api/{$endpoint}", array_merge(
                    ['api_key' => $this->apiKey],
                    $data
                ));

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception("Proxy returned error: " . $response->body());
        } catch (\Exception $e) {
            Log::error("Proxy Service Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => "Request failed: " . $e->getMessage()
            ];
        }
    }
}
