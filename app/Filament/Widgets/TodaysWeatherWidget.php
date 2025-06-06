<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Enums\OrderStatus;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class TodaysWeatherWidget extends Widget

{
    protected static string $view = 'filament.widgets.todays-weather-widget';
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = [
        'default' => 1,  // 1 column on mobile (full width since only 1 column total)
        'sm' => 1,       // 1/2 width on small screens (2 columns total)
        'md' => 1,       // 1/3 width on medium screens (3 columns total)
        'lg' => 1,       // 1/4 width on large screens (4 columns total)
        'xl' => 2,       // 2/6 width on extra large screens (6 columns total)
        '2xl' => 2,      // 2/8 width on 2xl screens (8 columns total)
    ];

    public function getWeatherData(): array
    {
        // Get today's orders that are actually being delivered (exclude will call, picked up, shipped, etc.)
        $nonDeliveryStatuses = [
            OrderStatus::WILL_CALL->value,
            OrderStatus::PICKED_UP->value,
            OrderStatus::SHIPPED->value,
            // OrderStatus::TRANSFERRED->value,
            // OrderStatus::TRANSFER->value,
            OrderStatus::DELIVERED->value,
            OrderStatus::CANCELLED->value,
            OrderStatus::COMPLETED->value,
            OrderStatus::INVOICED->value,
        ];

        $todaysOrders = Order::with('location')
            ->whereDate('assigned_delivery_date', today())
            ->whereHas('location')
            ->whereNotIn('status', $nonDeliveryStatuses)
            ->get();

        // Get unique cities with better normalization
        $cityMap = [];
        $todaysOrders->each(function ($order) use (&$cityMap) {
            if ($order->location && $order->location->city && $order->location->state) {
                // Normalize city name: trim, lowercase, remove extra spaces
                $normalizedCity = strtolower(trim(preg_replace('/\s+/', ' ', $order->location->city)));
                $normalizedState = strtoupper(trim($order->location->state));
                $key = $normalizedCity . ',' . $normalizedState;
                
                if (!isset($cityMap[$key])) {
                    $cityMap[$key] = [
                        'city' => $order->location->city, // Keep original formatting for display
                        'state' => $order->location->state,
                        'orders' => collect()
                    ];
                }
                $cityMap[$key]['orders']->push($order);
            }
        });

        // Always include Colma, CA even if no orders
        $colmaKey = 'colma,CA';
        if (!isset($cityMap[$colmaKey])) {
            $cityMap[$colmaKey] = [
                'city' => 'Colma',
                'state' => 'CA',
                'orders' => collect()
            ];
        }

        $weatherData = [];

        foreach ($cityMap as $cityInfo) {
            $cityKey = strtolower($cityInfo['city'] . '_' . $cityInfo['state']);
            
            // Cache weather data for 30 minutes
            $weather = Cache::remember(
                "weather_{$cityKey}_" . today()->format('Y-m-d'),
                now()->addMinutes(30),
                function () use ($cityInfo) {
                    return $this->fetchWeatherData($cityInfo['city'], $cityInfo['state']);
                }
            );

            if ($weather) {
                $weather['order_count'] = $cityInfo['orders']->count();
                $weatherData[] = $weather;
            }
        }

        // Sort so Colma appears first
        usort($weatherData, function($a, $b) {
            if ($a['city'] === 'Colma') return -1;
            if ($b['city'] === 'Colma') return 1;
            return strcmp($a['city'], $b['city']);
        });

        return $weatherData;
    }

    private function fetchWeatherData(string $city, string $state): ?array
    {
        try {
            // Using OpenWeatherMap One Call API 3.0 (free for first 1000 calls/day)
            $apiKey = config('services.openweather.api_key');
            
            // Debug logging
            logger()->info("Weather API Debug - City: {$city}, State: {$state}");
            logger()->info("API Key present: " . ($apiKey ? 'YES' : 'NO'));
            
            if (!$apiKey) {
                logger()->warning("No API key found, using mock data for {$city}, {$state}");
                return $this->getMockWeatherData($city, $state);
            }

            // First, get coordinates using the geocoding API (also free)
            $geoUrl = "http://api.openweathermap.org/geo/1.0/direct";
            $geoParams = [
                'q' => "{$city},{$state},US",
                'appid' => $apiKey,
                'limit' => 1
            ];
            
            logger()->info("Getting coordinates for {$city}, {$state}");
            $geoResponse = Http::get($geoUrl, $geoParams);
            
            if (!$geoResponse->successful() || empty($geoResponse->json())) {
                logger()->error("Geocoding failed for {$city}: " . $geoResponse->status());
                throw new \Exception("Geocoding failed");
            }
            
            $geoData = $geoResponse->json()[0];
            $lat = $geoData['lat'];
            $lon = $geoData['lon'];
            
            // Now use One Call API 3.0 (free tier)
            $url = "https://api.openweathermap.org/data/3.0/onecall";
            $params = [
                'lat' => $lat,
                'lon' => $lon,
                'appid' => $apiKey,
                'units' => 'imperial',
                'exclude' => 'minutely,hourly,daily,alerts' // Only get current weather
            ];
            
            logger()->info("Making One Call API request for {$city} at lat: {$lat}, lon: {$lon}");

            $response = Http::get($url, $params);
            
            logger()->info("API Response Status: " . $response->status());
            
            if ($response->successful()) {
                $data = $response->json();
                $current = $data['current'];
                logger()->info("API Success for {$city}: Temperature = " . $current['temp']);
                
                return [
                    'city' => $city,
                    'state' => $state,
                    'temperature' => round($current['temp']),
                    'feels_like' => round($current['feels_like']),
                    'description' => ucfirst($current['weather'][0]['description']),
                    'icon' => $current['weather'][0]['icon'],
                    'humidity' => $current['humidity'],
                    'wind_speed' => round($current['wind_speed']),
                    'visibility' => round(($current['visibility'] ?? 10000) / 1609.34, 1), // Convert meters to miles
                ];
            } else {
                logger()->error("API Error for {$city}: Status " . $response->status() . " - " . $response->body());
            }
        } catch (\Exception $e) {
            logger()->error("Weather API exception for {$city}: " . $e->getMessage());
        }

        logger()->warning("Falling back to mock data for {$city}, {$state}");
        // return $this->getMockWeatherData($city, $state);
    }

    // private function getMockWeatherData(string $city, string $state): array
    // {
    //     // Mock data for demonstration when API key is not available
    //     $mockConditions = [
    //         ['temp' => 72, 'desc' => 'Partly Cloudy', 'icon' => '02d'],
    //         ['temp' => 68, 'desc' => 'Sunny', 'icon' => '01d'],
    //         ['temp' => 75, 'desc' => 'Overcast', 'icon' => '04d'],
    //         ['temp' => 65, 'desc' => 'Light Rain', 'icon' => '10d'],
    //     ];

    //     $condition = $mockConditions[array_rand($mockConditions)];
        
    //     return [
    //         'city' => $city,
    //         'state' => $state,
    //         'temperature' => $condition['temp'],
    //         'feels_like' => $condition['temp'] + rand(-3, 3),
    //         'description' => $condition['desc'],
    //         'icon' => $condition['icon'],
    //         'humidity' => rand(40, 80),
    //         'wind_speed' => rand(5, 15),
    //         'visibility' => rand(8, 10),
    //     ];
    // }
} 