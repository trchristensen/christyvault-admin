<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.002 4.002 0 003 15z"></path>
                    </svg>
                    <span class="text-sm font-medium">Today's Weather</span>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Active deliveries
                </div>
            </div>
        </x-slot>

        @php
            $weatherData = $this->getWeatherData();
        @endphp

        @if(empty($weatherData))
            <div class="text-center py-6 text-gray-500">
                <svg class="w-8 h-8 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.002 4.002 0 003 15z"></path>
                </svg>
                <p class="text-sm font-medium text-gray-900 dark:text-white mb-1">No active deliveries today</p>
                <p class="text-xs text-gray-600 dark:text-gray-400">Will Call & Shipped orders excluded</p>
            </div>
        @else
            <div class="space-y-2 max-h-80 overflow-y-auto">
                @foreach($weatherData as $weather)
                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 dark:from-gray-800 dark:to-gray-700 rounded-lg p-3 border border-blue-200 dark:border-gray-600 hover:shadow-sm transition-shadow duration-200">
                        
                        <!-- Compact Single Row Layout -->
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center space-x-2 min-w-0 flex-1">
                                <!-- City & Order Count -->
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-sm text-gray-900 dark:text-white truncate">
                                        {{ $weather['city'] }}, {{ $weather['state'] }}
                                    </h3>
                                    @if($weather['order_count'] > 0)
                                        <div class="text-xs text-blue-600 dark:text-blue-400">
                                            {{ $weather['order_count'] }} {{ Str::plural('delivery', $weather['order_count']) }}
                                        </div>
                                    @else
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            No deliveries
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Weather Info -->
                            <div class="flex items-center space-x-3 text-right">
                                <!-- Temperature & Icon -->
                                <div class="flex items-center space-x-1">
                                    <img 
                                        src="https://openweathermap.org/img/w/{{ $weather['icon'] }}.png" 
                                        alt="{{ $weather['description'] }}"
                                        class="w-6 h-6"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';"
                                    >
                                    <span class="text-xs" style="display: none;">🌤️</span>
                                    <div>
                                        <div class="text-lg font-bold text-gray-900 dark:text-white">
                                            {{ $weather['temperature'] }}°
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-300 capitalize leading-none">
                                            {{ $weather['description'] }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Compact Details Row -->
                        <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
                            <div class="flex items-center space-x-4 gap-2">
                                <span><strong>{{ $weather['humidity'] }}%</strong> humidity</span>
                                <span><strong>{{ $weather['wind_speed'] }}</strong> mph wind</span>
                                <span><strong>{{ $weather['visibility'] }}</strong> mi vis</span>
                            </div>
                            
                            <!-- Weather Alerts (if any) -->
                            @php
                                $alerts = [];
                                if ($weather['temperature'] < 40) {
                                    $alerts[] = ['text' => 'Freezing', 'color' => 'text-red-600'];
                                } elseif ($weather['temperature'] > 85) {
                                    $alerts[] = ['text' => 'Hot', 'color' => 'text-orange-600'];
                                }
                                
                                if ($weather['wind_speed'] > 20) {
                                    $alerts[] = ['text' => 'Windy', 'color' => 'text-yellow-600'];
                                }
                                
                                if (Str::contains(strtolower($weather['description']), ['rain', 'storm', 'drizzle'])) {
                                    $alerts[] = ['text' => 'Wet', 'color' => 'text-blue-600'];
                                }
                                
                                if ($weather['visibility'] < 5) {
                                    $alerts[] = ['text' => 'Low vis', 'color' => 'text-gray-600'];
                                }
                            @endphp

                            @if(!empty($alerts))
                                <div class="flex space-x-1">
                                    @foreach($alerts as $alert)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $alert['color'] }} bg-gray-100 dark:bg-gray-700">
                                            ⚠️ {{ $alert['text'] }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Compact Footer -->
            <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                    <span>Updates every 30min</span>
                    <span>{{ now()->format('g:i A') }}</span>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>