<x-filament-widgets::widget>
    <x-filament::section class="admin-dashboard-attention-section">
        <x-slot name="heading">Needs attention</x-slot>

        <x-slot name="description">
            The short list of delivery and employee items that need a decision.
        </x-slot>

        @if ($items === [])
            <div class="om-empty-state">
                <x-filament::icon icon="heroicon-o-check-circle" />
                <div>
                    <strong>Nothing urgent is waiting.</strong>
                    <span>Trips, printing, scheduling, and time-off requests are caught up.</span>
                </div>
            </div>
        @else
            <div class="om-attention-list">
                @foreach ($items as $item)
                    <article class="om-attention-item is-{{ $item['tone'] }}">
                        <div class="om-attention-icon">
                            <x-filament::icon :icon="$item['icon']" />
                        </div>
                        <div class="om-attention-copy">
                            <span class="om-eyebrow">{{ $item['eyebrow'] }}</span>
                            <a href="{{ $item['url'] }}" class="om-attention-title">{{ $item['title'] }}</a>
                            @if (! empty($item['issues']))
                                <div class="om-attention-issues-list">
                                    @foreach ($item['issues'] as $issue)
                                        <div class="om-attention-issue">
                                            <span class="om-attention-issues">{{ $issue['label'] }}</span>
                                            @if (($issue['type'] ?? null) === 'assign_driver')
                                                <div class="om-inline-action">
                                                    <label class="sr-only" for="attention-driver-{{ $issue['trip_id'] }}">Driver</label>
                                                    <select
                                                        id="attention-driver-{{ $issue['trip_id'] }}"
                                                        wire:model="driverSelections.{{ $issue['trip_id'] }}"
                                                    >
                                                        <option value="">Select driver</option>
                                                        @foreach ($drivers as $driverId => $driverName)
                                                            <option value="{{ $driverId }}">{{ $driverName }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button
                                                        type="button"
                                                        wire:click="assignDriver({{ $issue['trip_id'] }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="assignDriver({{ $issue['trip_id'] }})"
                                                    >
                                                        Assign
                                                    </button>
                                                </div>
                                                @error('driverSelections.'.$issue['trip_id'])
                                                    <span class="om-inline-error">{{ $message }}</span>
                                                @enderror
                                            @elseif (($issue['type'] ?? null) === 'print_tags')
                                                <div class="om-inline-links">
                                                    @foreach ($issue['orders'] as $order)
                                                        <a href="{{ $order['url'] }}" target="_blank" rel="noopener">
                                                            Print {{ $order['number'] }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <span class="om-attention-issues">{{ $item['detail'] }}</span>
                            @endif
                            @if (! empty($item['summary']))
                                <span class="om-attention-summary">{{ $item['summary'] }}</span>
                            @endif
                            @if (! empty($item['orders']))
                                <div class="om-attention-orders">
                                    @foreach ($item['orders'] as $order)
                                        <a href="{{ $order['url'] }}">
                                            <strong>{{ $order['number'] }}</strong>
                                            <span>{{ $order['location'] }}</span>
                                            <span class="om-order-status">{{ $order['status'] }}</span>
                                            @if ($order['customer_order_number'])
                                                <span>PO {{ $order['customer_order_number'] }}</span>
                                            @endif
                                            @if ($order['plant'])
                                                <span class="om-mini-pill">{{ $order['plant'] }}</span>
                                            @endif
                                        </a>
                                    @endforeach
                                    @if ($item['more_orders'] > 0)
                                        <span class="om-more-orders">+{{ $item['more_orders'] }} more</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <a href="{{ $item['url'] }}" class="om-action-link">
                            {{ $item['action'] }} →
                        </a>
                    </article>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
