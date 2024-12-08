<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProxyService
{
    protected ?string $baseUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('proxy.base_url') ?? '';
        $this->apiKey = config('proxy.api_key') ?? '';
    }

    public function isHealthy(): bool
    {
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            Log::warning('Proxy configuration incomplete');
            throw new \Exception('Proxy service not properly configured');
        }

        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/health");

            if (!$response->successful()) {
                throw new \Exception('Proxy server returned error: ' . $response->body());
            }

            $data = $response->json();
            return $data['status'] === 'healthy';
        } catch (\Exception $e) {
            Log::error("Proxy health check failed: " . $e->getMessage());
            throw new \Exception('Cannot connect to proxy server: ' . $e->getMessage());
        }
    }

    public function post(string $endpoint, array $data): array
    {
        if (!$this->isHealthy()) {
            throw new \Exception('Proxy service is not healthy');
        }

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
