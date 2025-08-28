<!DOCTYPE html>
<html style="height: 100%;">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        html,
        body {
            height: 257mm;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }

        @page {
            margin: 0;
            size: letter portrait;
        }

        .page {
            page-break-after: always;
            padding: 20px;
            height: 220mm;
            display: block;
            page-orientation: upright;
            transform-origin: center;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        .page-header {
            text-align: center;
            margin-bottom: 10px;
            height: 15mm;
        }

        .week-grid {
            width: 100%;
            border-collapse: collapse;
            height: 222mm;
        }

        .day-column {
            width: 20%;
            vertical-align: top;
            border: 1px solid #ddd;
            padding: 10px;
            height: 100%;
        }

        .date-header {
            background: #ccc;
            padding: 5px;
            font-weight: bold;
            margin: -10px -10px 10px -10px;
            text-align: center;
        }

        .order {
            margin-bottom: 10px;
            padding: 5px;
            border: 1px solid #e5e7eb;
            background: white;
        }

        .order-header {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .order-details {
            font-size: 11px;
        }

        .no-orders {
            color: #666;
            font-style: italic;
            text-align: center;
            padding: 10px;
        }

        .order-section {
            display: flex;
            flex-direction: column;
            margin-bottom: 20px;
        }

        .order-section-header {
            font-weight: bold;
            padding: 5px;
            background: #ccc;
            margin-bottom: 10px;
        }

        .order-section-content {}
    </style>
</head>

<body>
    @php
        $firstWeek = $weekdays->take(5);
        $secondWeek = $weekdays->skip(5);

        use Propaganistas\LaravelPhone\PhoneNumber;
    @endphp

    <!-- First Week -->
    <div class="page">
        <div class="page-header">
            <h1 style="margin: 0;">Delivery Schedule - {{ $firstWeek->first()->format('M j') }} -
                {{ $firstWeek->last()->format('M j, Y') }}</h1>
        </div>

        <table class="week-grid">
            <tbody style="height: 100%">
                <tr style="height: 100%">
                    @foreach ($firstWeek as $date)
                        <td class="day-column">
                            <div class="date-header">
                                {{ $date->format('l, M j') }}
                            </div>

                            @php
                                $hasAnyOrders =
                                    isset($orders[$date->format('Y-m-d')]) &&
                                    ($orders[$date->format('Y-m-d')]->where('plant_location', 'colma_main')->count() >
                                        0 ||
                                        $orders[$date->format('Y-m-d')]
                                            ->where('plant_location', 'tulare_plant')
                                            ->count() > 0 ||
                                        $orders[$date->format('Y-m-d')]
                                            ->where('plant_location', 'colma_locals')
                                            ->count() > 0);
                            @endphp

                            @if (!$hasAnyOrders)
                                <div class="no-orders">No deliveries scheduled</div>
                            @endif

                            <!-- Colma Section -->
                            @if (isset($orders[$date->format('Y-m-d')]) &&
                                    $orders[$date->format('Y-m-d')]->where('plant_location', 'colma_main')->count() > 0)
                                <div class="order-section colma">
                                    <div class="order-section-header">Colma</div>
                                    <div class="order-section-content">
                                        @foreach ($orders[$date->format('Y-m-d')]->where('plant_location', 'colma_main') as $order)
                                            <div class="order">
                                                <div class="order-header">
                                                    {{ $order->location?->name ?? 'No Location' }}
                                                </div>
                                                <div class="order-details">
                                                    @if ($order->location)
                                                        {{-- <div>{{ $order->location->address_line1 }}</div> --}}
                                                        <div>{{ $order->location->city }}, {{ $order->location->state }}
                                                        </div>
                                                    @endif
                                                    <div>
                                                        @php
                                                            if ($order->location && $order->location->preferredDeliveryContact) {
                                                                try {
                                                                    $formattedPhone = (new PhoneNumber(
                                                                        $order->location->preferredDeliveryContact->phone,
                                                                        'US'
                                                                    ))->formatNational();
                                                                } catch (\Exception $e) {
                                                                    $formattedPhone = $order->location->preferredDeliveryContact->phone;
                                                                }
                                                                echo $formattedPhone;
                                                            } else {
                                                                echo '';
                                                            }
                                                        @endphp
                                                    </div>
                                                    {{-- @if ($order->special_instructions)
                                                        <div>Notes: {{ $order->special_instructions }}</div>
                                                    @endif --}}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- Tulare Section -->
                            @if (isset($orders[$date->format('Y-m-d')]) &&
                                    $orders[$date->format('Y-m-d')]->where('plant_location', 'tulare_plant')->count() > 0)
                                <div class="order-section tulare">
                                    <div class="order-section-header">Tulare</div>
                                    <div class="order-section-content">
                                        @foreach ($orders[$date->format('Y-m-d')]->where('plant_location', 'tulare_plant') as $order)
                                            <div class="order">
                                                <div class="order-header">
                                                    {{ $order->location?->name ?? 'No Location' }}
                                                </div>
                                                <div class="order-details">
                                                    @if ($order->location)
                                                        {{-- <div>{{ $order->location->address_line1 }}</div> --}}
                                                        <div>{{ $order->location->city }},
                                                            {{ $order->location->state }}</div>
                                                    @endif
                                                    <div>
                                                        @php
                                                            if ($order->location && $order->location->preferredDeliveryContact) {
                                                                try {
                                                                    $formattedPhone = (new PhoneNumber(
                                                                        $order->location->preferredDeliveryContact->phone,
                                                                        'US'
                                                                    ))->formatNational();
                                                                } catch (\Exception $e) {
                                                                    $formattedPhone = $order->location->preferredDeliveryContact->phone;
                                                                }
                                                                echo $formattedPhone;
                                                            } else {
                                                                echo '';
                                                            }
                                                        @endphp
                                                    </div>
                                                    {{-- @if ($order->special_instructions)
                                                        <div>Notes: {{ $order->special_instructions }}</div>
                                                    @endif --}}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- Locals Section -->
                            @if (isset($orders[$date->format('Y-m-d')]) &&
                                    $orders[$date->format('Y-m-d')]->where('plant_location', 'colma_locals')->count() > 0)
                                <div class="order-section locals">
                                    <div class="order-section-header">Locals</div>
                                    <div class="order-section-content">
                                        @foreach ($orders[$date->format('Y-m-d')]->where('plant_location', 'colma_locals') as $order)
                                            <div class="order">
                                                <div class="order-header">
                                                    {{ $order->location?->name ?? 'No Location' }}
                                                </div>
                                                <div class="order-details">
                                                    @if ($order->location)
                                                        {{-- <div>{{ $order->location->address_line1 }}</div> --}}
                                                        <div>{{ $order->location->city }},
                                                            {{ $order->location->state }}</div>
                                                    @endif
                                                    <div>
                                                        @php
                                                            if ($order->location && $order->location->preferredDeliveryContact) {
                                                                try {
                                                                    $formattedPhone = (new PhoneNumber(
                                                                        $order->location->preferredDeliveryContact->phone,
                                                                        'US'
                                                                    ))->formatNational();
                                                                } catch (\Exception $e) {
                                                                    $formattedPhone = $order->location->preferredDeliveryContact->phone;
                                                                }
                                                                echo $formattedPhone;
                                                            } else {
                                                                echo '';
                                                            }
                                                        @endphp
                                                    </div>
                                                    {{-- @if ($order->special_instructions)
                                                        <div>Notes: {{ $order->special_instructions }}</div>
                                                    @endif --}}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Second Week -->
    <div class="page">
        <div class="page-header">
            <h1 style="margin: 0;">Delivery Schedule - {{ $secondWeek->first()->format('M j') }} -
                {{ $secondWeek->last()->format('M j, Y') }}</h1>
        </div>

        <table class="week-grid">
            <tbody style="height: 100%">
                <tr style="height: 100%">
                    @foreach ($secondWeek as $date)
                        <td class="day-column">
                            <div class="date-header">
                                {{ $date->format('l, M j') }}
                            </div>

                            @php
                                $hasAnyOrders =
                                    isset($orders[$date->format('Y-m-d')]) &&
                                    ($orders[$date->format('Y-m-d')]->where('plant_location', 'colma_main')->count() >
                                        0 ||
                                        $orders[$date->format('Y-m-d')]
                                            ->where('plant_location', 'tulare_plant')
                                            ->count() > 0 ||
                                        $orders[$date->format('Y-m-d')]
                                            ->where('plant_location', 'colma_locals')
                                            ->count() > 0);
                            @endphp

                            @if (!$hasAnyOrders)
                                <div class="no-orders">No deliveries scheduled</div>
                            @endif

                            <!-- Colma Section -->
                            @if (isset($orders[$date->format('Y-m-d')]) &&
                                    $orders[$date->format('Y-m-d')]->where('plant_location', 'colma_main')->count() > 0)
                                <div class="order-section colma">
                                    <div class="order-section-header">Colma</div>
                                    <div class="order-section-content">
                                        @foreach ($orders[$date->format('Y-m-d')]->where('plant_location', 'colma_main') as $order)
                                            <div class="order">
                                                <div class="order-header">
                                                    {{ $order->location?->name ?? 'No Location' }}
                                                </div>
                                                <div class="order-details">
                                                    @if ($order->location)
                                                        {{-- <div>{{ $order->location->address_line1 }}</div> --}}
                                                        <div>{{ $order->location->city }},
                                                            {{ $order->location->state }}</div>
                                                    @endif
                                                    <div>
                                                        @php
                                                            if ($order->location && $order->location->preferredDeliveryContact) {
                                                                try {
                                                                    $formattedPhone = (new PhoneNumber(
                                                                        $order->location->preferredDeliveryContact->phone,
                                                                        'US'
                                                                    ))->formatNational();
                                                                } catch (\Exception $e) {
                                                                    $formattedPhone = $order->location->preferredDeliveryContact->phone;
                                                                }
                                                                echo $formattedPhone;
                                                            } else {
                                                                echo '';
                                                            }
                                                        @endphp
                                                    </div>
                                                    {{-- @if ($order->special_instructions)
                                                        <div>Notes: {{ $order->special_instructions }}</div>
                                                    @endif --}}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- Tulare Section -->
                            @if (isset($orders[$date->format('Y-m-d')]) &&
                                    $orders[$date->format('Y-m-d')]->where('plant_location', 'tulare_plant')->count() > 0)
                                <div class="order-section tulare">
                                    <div class="order-section-header">Tulare</div>
                                    <div class="order-section-content">
                                        @foreach ($orders[$date->format('Y-m-d')]->where('plant_location', 'tulare_plant') as $order)
                                            <div class="order">
                                                <div class="order-header">
                                                    {{ $order->location?->name ?? 'No Location' }}
                                                </div>
                                                <div class="order-details">
                                                    @if ($order->location)
                                                        {{-- <div>{{ $order->location->address_line1 }}</div> --}}
                                                        <div>{{ $order->location->city }},
                                                            {{ $order->location->state }}</div>
                                                    @endif
                                                    <div>
                                                        @php
                                                            if ($order->location && $order->location->preferredDeliveryContact) {
                                                                try {
                                                                    $formattedPhone = (new PhoneNumber(
                                                                        $order->location->preferredDeliveryContact->phone,
                                                                        'US'
                                                                    ))->formatNational();
                                                                } catch (\Exception $e) {
                                                                    $formattedPhone = $order->location->preferredDeliveryContact->phone;
                                                                }
                                                                echo $formattedPhone;
                                                            } else {
                                                                echo '';
                                                            }
                                                        @endphp
                                                    </div>
                                                    {{-- @if ($order->special_instructions)
                                                        <div>Notes: {{ $order->special_instructions }}</div>
                                                    @endif --}}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- Locals Section -->
                            @if (isset($orders[$date->format('Y-m-d')]) &&
                                    $orders[$date->format('Y-m-d')]->where('plant_location', 'colma_locals')->count() > 0)
                                <div class="order-section locals">
                                    <div class="order-section-header">Locals</div>
                                    <div class="order-section-content">
                                        @foreach ($orders[$date->format('Y-m-d')]->where('plant_location', 'colma_locals') as $order)
                                            <div class="order">
                                                <div class="order-header">
                                                    {{ $order->location?->name ?? 'No Location' }}
                                                </div>
                                                <div class="order-details">
                                                    @if ($order->location)
                                                        {{-- <div>{{ $order->location->address_line1 }}</div> --}}
                                                        <div>{{ $order->location->city }},
                                                            {{ $order->location->state }}</div>
                                                    @endif
                                                    <div>
                                                        @php
                                                            if ($order->location && $order->location->preferredDeliveryContact) {
                                                                try {
                                                                    $formattedPhone = (new PhoneNumber(
                                                                        $order->location->preferredDeliveryContact->phone,
                                                                        'US'
                                                                    ))->formatNational();
                                                                } catch (\Exception $e) {
                                                                    $formattedPhone = $order->location->preferredDeliveryContact->phone;
                                                                }
                                                                echo $formattedPhone;
                                                            } else {
                                                                echo '';
                                                            }
                                                        @endphp
                                                    </div>
                                                    {{-- @if ($order->special_instructions)
                                                        <div>Notes: {{ $order->special_instructions }}</div>
                                                    @endif --}}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>
</body>

</html>
