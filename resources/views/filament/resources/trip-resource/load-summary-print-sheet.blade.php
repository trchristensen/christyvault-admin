@php
    $summary = $result['summary'];
    $vehicle = $result['vehicle_configuration'];
    $maximumWeight = (float) ($summary['maximum_product_weight_lbs'] ?? 0);
    $knownWeight = (float) ($summary['known_weight_lbs'] ?? 0);
    $fillAllocations = collect($fillAllocations ?? []);
    $warningMessages = collect($result['warnings'] ?? [])->pluck('message')->unique()->values();
    $isMultiStop = count($result['stops']) > 1;
    $needsReview =
        !($result['ready_for_automatic_placement'] ?? false)
        || !($diagram['available'] ?? false)
        || count($diagram['unplaced'] ?? []) > 0;
    $printOrderNumbers = $trip->orderedDeliveryOrders()
        ->pluck('order_number')
        ->filter()
        ->join(' · ');
    $primaryStop = $result['stops'][0] ?? null;
    $rackCount = count($diagram['racks'] ?? []);
    $flatbedCapacity = (int) ($diagram['flatbed_pallet_capacity'] ?? 0);
    $hasFlatbedCargo = collect($diagram['flatbed_pallets'] ?? [])->filter()->isNotEmpty();
@endphp

<style>
    .cv-print-load-sheet {
        color: #111;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 14px;
        line-height: 1.2;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    .cv-print-load-sheet,
    .cv-print-load-sheet * {
        box-sizing: border-box;
    }

    .cv-print-load-sheet h1,
    .cv-print-load-sheet h2,
    .cv-print-load-sheet p {
        margin: 0;
    }

    .cv-print-sheet-header {
        align-items: flex-end;
        border-bottom: 2px solid #111;
        display: flex;
        gap: 16px;
        justify-content: space-between;
        padding-bottom: 6px;
    }

    .cv-print-title {
        font-size: 24px;
        font-weight: 800;
        letter-spacing: .04em;
    }

    .cv-print-trip-meta {
        font-size: 12px;
        margin-top: 2px;
    }

    .cv-print-header-right {
        display: grid;
        gap: 1px;
        text-align: right;
    }

    .cv-print-location {
        font-size: 14px;
        font-weight: 800;
    }

    .cv-print-weight {
        font-size: 18px;
        font-weight: 800;
    }

    .cv-print-stop-row {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 5px 14px;
        padding: 6px 0;
    }

    .cv-print-stop {
        align-items: baseline;
        display: inline-flex;
        gap: 5px;
    }

    .cv-print-stop-code {
        border: 1px solid #111;
        font-weight: 800;
        padding: 1px 4px;
    }

    .cv-print-fill-row {
        align-items: center;
        border: 1.5px solid #111;
        display: flex;
        gap: 12px;
        justify-content: space-between;
        margin-bottom: 7px;
        padding: 5px 7px;
    }

    .cv-print-fill-products {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 14px;
    }

    .cv-print-fill-quantity {
        flex: 0 0 auto;
        font-size: 16px;
        font-weight: 800;
    }

    .cv-print-review {
        border: 2px solid #111;
        font-size: 14px;
        font-weight: 800;
        margin-bottom: 7px;
        padding: 5px 7px;
        text-align: center;
    }

    .cv-print-section {
        break-inside: avoid;
        margin-top: 7px;
        page-break-inside: avoid;
    }

    .cv-print-section-heading {
        align-items: baseline;
        display: flex;
        gap: 10px;
        justify-content: space-between;
        margin-bottom: 3px;
    }

    .cv-print-section-title {
        font-size: 14px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .cv-print-direction {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .cv-print-rack-grid {
        align-items: end;
        display: grid;
        gap: 5px;
    }

    .cv-print-rack {
        display: grid;
        grid-template-rows: 1fr auto;
        min-width: 0;
    }

    .cv-print-rack-body {
        border: 2px solid #111;
        display: grid;
    }

    .cv-print-rack-cell {
        align-items: center;
        border-bottom: 1px solid #111;
        display: flex;
        flex-direction: column;
        font-size: 18px;
        font-weight: 800;
        justify-content: center;
        min-height: 0;
        overflow: hidden;
        padding: 1px;
        position: relative;
        text-align: center;
    }

    .cv-print-rack-cell:last-child {
        border-bottom: 0;
    }

    .cv-print-rack-cell-empty {
        font-weight: 400;
    }

    .cv-print-cell-meta {
        font-size: 9px;
        font-weight: 700;
        line-height: 1;
        margin-top: 1px;
        text-transform: uppercase;
    }

    .cv-print-cell-stop {
        border: 1px solid #111;
        font-size: 9px;
        left: 2px;
        line-height: 1;
        padding: 1px 2px;
        position: absolute;
        top: 2px;
    }

    .cv-print-empty-rack {
        align-items: center;
        border: 2px dashed #111;
        display: flex;
        font-size: 14px;
        height: 124px;
        justify-content: center;
        text-align: center;
    }

    .cv-print-rack-label,
    .cv-print-deck-label {
        display: grid;
        font-size: 10px;
        gap: 1px;
        margin-top: 3px;
        text-align: center;
    }

    .cv-print-rack-label strong,
    .cv-print-deck-label strong {
        font-size: 11px;
    }

    .cv-print-flatbed-layout {
        align-items: start;
        display: grid;
        gap: 12px;
        grid-template-columns: 1fr 3fr;
    }

    .cv-print-flatbed-note {
        font-size: 12px;
        margin-top: 4px;
    }

    .cv-print-flatbed-grid {
        display: grid;
        gap: 6px;
    }

    .cv-print-deck-position {
        display: grid;
        grid-template-rows: 76px auto;
        min-width: 0;
    }

    .cv-print-deck-cargo,
    .cv-print-deck-open {
        align-items: center;
        border: 2px solid #111;
        display: flex;
        flex-direction: column;
        font-size: 12px;
        font-weight: 800;
        justify-content: center;
        overflow: hidden;
        padding: 4px 4px 8px;
        position: relative;
        text-align: center;
        text-transform: uppercase;
    }

    .cv-print-deck-open {
        border-style: dashed;
        font-size: 14px;
        font-weight: 700;
        padding: 4px;
    }

    .cv-print-deck-code,
    .cv-print-deck-instruction {
        position: relative;
        z-index: 2;
    }

    .cv-print-deck-instruction {
        font-size: 10px;
        margin-top: 2px;
    }

    .cv-print-strap {
        height: 100%;
        left: 0;
        opacity: .55;
        position: absolute;
        top: 0;
        width: 100%;
        z-index: 1;
    }

    .cv-print-strap-line {
        fill: #555;
    }

    .cv-print-strap-buckle {
        fill: #fff;
        stroke: #111;
        stroke-width: 1.5;
        vector-effect: non-scaling-stroke;
    }

    .cv-print-pallet-base {
        bottom: -2px;
        height: 11px;
        left: -2px;
        position: absolute;
        width: calc(100% + 4px);
        z-index: 3;
    }

    .cv-print-pallet-base path {
        fill: #111;
    }

    .cv-print-key {
        border-top: 1.5px solid #111;
        display: grid;
        gap: 5px 14px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        margin-top: 8px;
        padding-top: 7px;
    }

    .cv-print-key-item {
        align-items: start;
        display: grid;
        gap: 5px;
        grid-template-columns: auto minmax(0, 1fr);
        min-width: 0;
    }

    .cv-print-code {
        border: 1.5px solid #111;
        font-size: 10px;
        font-weight: 800;
        min-width: 24px;
        padding: 2px 4px;
        text-align: center;
    }

    .cv-print-product {
        min-width: 0;
    }

    .cv-print-product-name {
        font-size: 12px;
    }

    .cv-print-product-meta {
        font-size: 10px;
        margin-top: 1px;
    }

    .cv-print-product-note {
        font-size: 10px;
        margin-top: 2px;
    }

    .cv-print-loose {
        border-top: 1px solid #111;
        display: flex;
        flex-wrap: wrap;
        gap: 4px 14px;
        margin-top: 6px;
        padding-top: 5px;
    }

    .cv-print-alert {
        border: 2px solid #111;
        break-inside: avoid;
        margin-top: 7px;
        padding: 5px 7px;
        page-break-inside: avoid;
    }

    .cv-print-alert-title {
        font-size: 13px;
        font-weight: 800;
        margin-bottom: 2px;
        text-transform: uppercase;
    }

    .cv-print-alert ul {
        margin: 2px 0 0;
        padding-left: 16px;
    }

    .cv-print-unplaced-table {
        border-collapse: collapse;
        margin-top: 3px;
        width: 100%;
    }

    .cv-print-unplaced-table th,
    .cv-print-unplaced-table td {
        border-top: 1px solid #111;
        padding: 3px 4px;
        text-align: left;
        vertical-align: top;
    }

    .cv-print-unplaced-table th {
        font-size: 10px;
        text-transform: uppercase;
    }

    @media screen and (max-width: 760px) {
        .cv-print-sheet-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .cv-print-header-right {
            text-align: left;
        }

        .cv-print-rack-grid {
            grid-template-columns: repeat(4, minmax(64px, 1fr)) !important;
        }

        .cv-print-flatbed-layout {
            grid-template-columns: 1fr;
        }

        .cv-print-key {
            grid-template-columns: 1fr;
        }
    }
</style>

<main class="cv-print-load-sheet cv-load-sheet-print">
    <header class="cv-print-sheet-header">
        <div>
            <h1 class="cv-print-title">LOAD PLAN</h1>
            <div class="cv-print-trip-meta">
                {{ $trip->trip_number }}
                @if ($trip->scheduled_date)
                    · {{ $trip->scheduled_date->format('D M j, Y') }}
                @endif
                @if ($printOrderNumbers)
                    · {{ $printOrderNumbers }}
                @endif
            </div>
        </div>

        <div class="cv-print-header-right">
            <span class="cv-print-location">
                @if ($isMultiStop)
                    {{ count($result['stops']) }} delivery stops
                @else
                    {{ $primaryStop['location_name'] ?? 'Delivery location unavailable' }}
                @endif
            </span>
            <span class="cv-print-weight">
                {{ number_format($knownWeight, 0) }} lb
                @if ($maximumWeight > 0)
                    / {{ number_format($maximumWeight, 0) }} lb max
                @endif
            </span>
            <span>
                {{ $vehicle['name'] ?? 'Vehicle configuration missing' }}
                @if ($vehicle['piggyback_forklift_onboard'] ?? false)
                    · forklift onboard
                @endif
            </span>
        </div>
    </header>

    <div class="cv-print-stop-row">
        @foreach ($result['stops'] as $stop)
            <span class="cv-print-stop">
                <span class="cv-print-stop-code">S{{ $stop['sequence'] }}</span>
                <strong>{{ $stop['location_name'] ?: 'Location unavailable' }}</strong>
                <span>· {{ $stop['order_number'] ?: 'Order '.$stop['order_id'] }}</span>
            </span>
        @endforeach
        <span>· {{ number_format($summary['product_units']) }} product units</span>
        <strong>· Unload from rear</strong>
    </div>

    @if ($needsReview)
        <div class="cv-print-review">MANUAL REVIEW REQUIRED — CHECK LOAD WARNINGS BEFORE LOADING</div>
    @endif

    @if ($fillAllocations->isNotEmpty())
        <div class="cv-print-fill-row">
            <div class="cv-print-fill-products">
                @foreach ($fillAllocations as $allocation)
                    <span>
                        <strong>FILL LOAD:</strong>
                        {{ $allocation['sku'] }}
                        @if (filled($allocation['name'] ?? null))
                            {{ $allocation['name'] }}
                        @endif
                    </span>
                @endforeach
            </div>
            <span class="cv-print-fill-quantity">
                {{ $fillAllocations->map(fn ($allocation) => ($allocation['resolved'] ? number_format($allocation['planned_quantity']) : '?').' planned')->join(' · ') }}
            </span>
        </div>
    @endif

    @if ($diagram['available'])
        <section class="cv-print-section">
            <div class="cv-print-section-heading">
                <h2 class="cv-print-section-title">1 · Racks</h2>
                <span class="cv-print-direction">Front / tractor → rear / unload first</span>
            </div>

            <div class="cv-print-rack-grid"
                style="grid-template-columns: repeat({{ max(1, $rackCount) }}, minmax(0, 1fr));">
                @foreach ($diagram['racks'] as $rack)
                    <div class="cv-print-rack">
                        @if ($rack['type_code'])
                            <div class="cv-print-rack-body"
                                style="grid-template-rows: repeat({{ $rack['level_count'] }}, 42px);">
                                @foreach (array_reverse($rack['cells'], true) as $cell)
                                    @if ($cell)
                                        <div class="cv-print-rack-cell">
                                            @if ($isMultiStop)
                                                <span class="cv-print-cell-stop">S{{ $cell['stop_sequence'] }}</span>
                                            @endif
                                            <span>{{ $cell['code'] }}</span>
                                            @if (($cell['is_pallet_level'] ?? false) || ($cell['component'] ?? null) === 'half')
                                                <span class="cv-print-cell-meta">
                                                    @if ($cell['is_pallet_level'] ?? false)
                                                        {{ count($cell['pallets']) }}
                                                        {{ Str::plural('pallet', count($cell['pallets'])) }}
                                                    @endif
                                                    @if (($cell['component'] ?? null) === 'half')
                                                        Pair {{ $cell['split_pair'] }}
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="cv-print-rack-cell cv-print-rack-cell-empty">—</div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div class="cv-print-empty-rack">EMPTY RACK</div>
                        @endif

                        <div class="cv-print-rack-label">
                            <strong>R{{ $rack['number'] }}</strong>
                            <span>
                                {{ number_format($rack['product_weight_lbs'], 0) }}
                                lb{{ $rack['has_unknown_weight'] ? ' + ?' : '' }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        @if ($flatbedCapacity > 0 && $hasFlatbedCargo)
            <section class="cv-print-section cv-print-flatbed-layout">
                <div>
                    <h2 class="cv-print-section-title">2 · Flatbed deck</h2>
                    <p class="cv-print-flatbed-note">
                        Immediately behind R{{ $rackCount }} · secure every occupied spot
                    </p>
                </div>

                <div class="cv-print-flatbed-grid"
                    style="grid-template-columns: repeat({{ $flatbedCapacity }}, minmax(0, 1fr));">
                    @for ($spot = 1; $spot <= $flatbedCapacity; $spot++)
                        @php
                            $pallet = $diagram['flatbed_pallets'][$spot - 1] ?? null;
                            $isDirectFlatbed = (bool) ($pallet['is_direct_flatbed'] ?? false);
                        @endphp
                        <div class="cv-print-deck-position">
                            @if ($pallet)
                                <div class="cv-print-deck-cargo">
                                    <span class="cv-print-deck-code">{{ $pallet['code'] }}</span>
                                    <span class="cv-print-deck-instruction">
                                        {{ $isDirectFlatbed ? 'Secure to deck' : 'Strap pallet to deck' }}
                                    </span>
                                    <svg class="cv-print-strap" viewBox="0 0 100 100"
                                        preserveAspectRatio="none" aria-hidden="true">
                                        <path class="cv-print-strap-line" d="M17 0h3v100h-3z" />
                                        <rect class="cv-print-strap-buckle" x="14" y="82" width="9"
                                            height="8" rx="1.5" />
                                    </svg>
                                    @if (! $isDirectFlatbed)
                                        <svg class="cv-print-pallet-base" viewBox="0 0 100 14"
                                            preserveAspectRatio="none" aria-hidden="true">
                                            <path
                                                d="M0 0h100v4H0zM5 4h14v7H5zM43 4h14v7H43zM81 4h14v7H81zM0 11h100v3H0z" />
                                        </svg>
                                    @endif
                                </div>
                            @else
                                <div class="cv-print-deck-open">OPEN</div>
                            @endif

                            <div class="cv-print-deck-label">
                                <strong>P{{ $spot }}</strong>
                                @if ($pallet)
                                    <span>
                                        {{ $pallet['total_weight_lbs'] === null ? '?' : number_format($pallet['total_weight_lbs'], 0) }}
                                        lb
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endfor
                </div>
            </section>
        @endif

        <section class="cv-print-key" aria-label="Product code key">
            @forelse ($diagram['legend'] as $entry)
                <div class="cv-print-key-item">
                    <span class="cv-print-code">{{ $entry['code'] }}</span>
                    <div class="cv-print-product">
                        <div class="cv-print-product-name">
                            <strong>{{ $entry['sku'] }}</strong> · {{ $entry['name'] }}
                        </div>
                        @if (($entry['unit_weight_lbs'] ?? null) !== null)
                            <div class="cv-print-product-meta">
                                {{ number_format($entry['unit_weight_lbs'], 0) }} lb /
                                {{ str($entry['unit_of_measure'] ?? 'unit')->replace('_', ' ') }}
                            </div>
                        @endif
                        @if (($entry['units_per_rack_position'] ?? 1) > 1)
                            <div class="cv-print-product-note">
                                Up to {{ $entry['units_per_rack_position'] }} per rack position
                            </div>
                        @endif
                        @if (($entry['placement_strategy'] ?? null) === \App\Models\LoadingProfile::PLACEMENT_FULL_TOP_SPLIT_BOTTOM_PAIR)
                            <div class="cv-print-product-note">
                                ½{{ $entry['code'] }} = one half; two halves count as one product
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <span>No products were placed.</span>
            @endforelse
        </section>

        @if (! empty($diagram['non_rack_cargo']))
            <section class="cv-print-loose">
                <strong>Loose / boxed cargo:</strong>
                @foreach ($diagram['non_rack_cargo'] as $item)
                    <span>
                        {{ number_format($item['quantity']) }}× {{ $item['sku'] }}
                        · S{{ $item['stop_sequence'] }}
                        · {{ $item['total_weight_lbs'] === null ? 'weight pending' : number_format($item['total_weight_lbs'], 0).' lb' }}
                    </span>
                @endforeach
            </section>
        @endif
    @else
        <section class="cv-print-alert">
            <div class="cv-print-alert-title">Diagram unavailable</div>
            <p>{{ $diagram['message'] }}</p>
        </section>
    @endif

    @if (! empty($diagram['unplaced']))
        <section class="cv-print-alert">
            <div class="cv-print-alert-title">Not shown on the truck — manual placement required</div>
            <table class="cv-print-unplaced-table">
                <thead>
                    <tr>
                        <th>Qty</th>
                        <th>Stop</th>
                        <th>Product</th>
                        <th>Why it is unplaced</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($diagram['unplaced'] as $item)
                        <tr>
                            <td>{{ number_format($item['quantity']) }}</td>
                            <td>{{ $item['stop_sequence'] }}</td>
                            <td><strong>{{ $item['sku'] }}</strong> · {{ $item['name'] }}</td>
                            <td>{{ $item['reason'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    @if ($warningMessages->isNotEmpty())
        <section class="cv-print-alert">
            <div class="cv-print-alert-title">Load checks</div>
            <ul>
                @foreach ($warningMessages as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </section>
    @endif
</main>
