<x-filament-widgets::widget>
    <x-filament::section class="admin-dashboard-weather-section">
        <x-slot name="heading">7-day delivery weather</x-slot>

        <x-slot name="description">
            Colma and Tulare every day, plus nonlocal destinations on their scheduled delivery day.
        </x-slot>

        <div class="om-weather-week" aria-label="Seven-day delivery weather forecast">
            @foreach ($days as $day)
                <article class="om-weather-day {{ $day['date']->isToday() ? 'is-today' : '' }}">
                    <header class="om-weather-day-header">
                        <div>
                            <span class="om-eyebrow">{{ $day['label'] }}</span>
                            <h3>{{ $day['date']->format('M j') }}</h3>
                        </div>
                        <span>{{ count($day['destinations']) }} {{ Str::plural('area', count($day['destinations'])) }}</span>
                    </header>

                    <div class="om-weather-location-list">
                        @foreach ($day['destinations'] as $destination)
                            @php($weather = $destination['weather'])
                            @php($current = $day['date']->isToday() ? ($weather['current'] ?? null) : null)
                            <div class="om-weather-location {{ $destination['is_plant'] ? 'is-plant' : 'is-delivery' }}">
                                <div class="om-weather-location-heading">
                                    <div>
                                        <strong>{{ $destination['city'] }}</strong>
                                        <span>{{ $destination['state'] }}</span>
                                    </div>
                                    @if ($destination['stop_count'] > 0)
                                        <span class="om-weather-stop-count">
                                            {{ trans_choice(':count stop|:count stops', $destination['stop_count'], ['count' => $destination['stop_count']]) }}
                                        </span>
                                    @elseif ($destination['is_plant'])
                                        <span class="om-weather-plant-label">Plant</span>
                                    @endif
                                </div>

                                @if ($weather)
                                    <div class="om-weather-reading">
                                        <span class="om-weather-symbol" aria-hidden="true">{{ $current['symbol'] ?? $weather['symbol'] }}</span>
                                        <div class="om-weather-temperature">
                                            @if ($current)
                                                <strong>{{ $current['temperature'] !== null ? $current['temperature'].'°' : '—' }}</strong>
                                                <span>Now</span>
                                            @else
                                                <strong>H {{ $weather['high'] !== null ? $weather['high'].'°' : '—' }}</strong>
                                                <span>L {{ $weather['low'] !== null ? $weather['low'].'°' : '—' }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <span class="om-weather-description">{{ $current['description'] ?? $weather['description'] }}</span>

                                    <div class="om-weather-details">
                                        @if ($current)
                                            <span>H {{ $weather['high'] !== null ? $weather['high'].'°' : '—' }} · L {{ $weather['low'] !== null ? $weather['low'].'°' : '—' }}</span>
                                        @endif
                                        <span>{{ $weather['rain_chance'] }}% rain</span>
                                        @php($wind = $current['wind_speed'] ?? $weather['wind_speed'])
                                        <span>{{ $wind !== null ? $wind.' mph wind' : 'Wind unavailable' }}</span>
                                    </div>

                                    @if ($weather['warnings'] !== [])
                                        <div class="om-weather-warnings">
                                            @foreach ($weather['warnings'] as $warning)
                                                <span>{{ $warning }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    <div class="om-weather-unavailable">
                                        <span aria-hidden="true">—</span>
                                        <span>Forecast unavailable</span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>

        <div class="om-weather-footer">
            <span>Local Colma-lane deliveries are excluded from destination forecasts.</span>
            @if ($updated_at)
                <span>Updated {{ $updated_at->diffForHumans() }}</span>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
