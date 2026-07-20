@php
    $summary = $result['summary'];
    $vehicle = $result['vehicle_configuration'];
    $maximumWeight = (float) ($summary['maximum_product_weight_lbs'] ?? 0);
    $knownWeight = (float) ($summary['known_weight_lbs'] ?? 0);
    $weightPercent = $maximumWeight > 0 ? min(100, ($knownWeight / $maximumWeight) * 100) : 0;
    $needsReview =
        !$result['ready_for_automatic_placement'] || !$diagram['available'] || count($diagram['unplaced']) > 0;
    $warningMessages = collect($result['warnings'])->pluck('message')->unique()->values();
    $fillAllocations = collect($fillAllocations ?? []);
    $isMultiStop = count($result['stops']) > 1;
@endphp

<style>
    .cv-load-sheet {
        --cv-ink: #172033;
        --cv-muted: #657085;
        --cv-line: #d9dee8;
        --cv-soft: #f5f7fa;
        --cv-blue: #2457d6;
        --cv-diagram-bg: #fbfcfe;
        color: var(--cv-ink);
        font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        font-size: 14px;
        line-height: 1.4;
    }

    .cv-load-sheet * {
        box-sizing: border-box;
    }

    .cv-load-sheet h2,
    .cv-load-sheet h3,
    .cv-load-sheet p {
        margin: 0;
    }

    .cv-sheet-header {
        align-items: center;
        background: linear-gradient(135deg, #f7f9fc 0%, #eef3fb 100%);
        border: 1px solid var(--cv-line);
        border-radius: 14px;
        display: flex;
        gap: 16px;
        justify-content: space-between;
        padding: 14px 16px;
    }

    .cv-vehicle-name {
        font-size: 17px;
        font-weight: 750;
    }

    .cv-vehicle-meta {
        color: var(--cv-muted);
        font-size: 13px;
        margin-top: 2px;
    }

    .cv-status {
        align-items: center;
        border-radius: 999px;
        display: inline-flex;
        flex: 0 0 auto;
        font-size: 12px;
        font-weight: 750;
        gap: 7px;
        padding: 7px 11px;
    }

    .cv-status::before {
        border-radius: 50%;
        content: "";
        height: 8px;
        width: 8px;
    }

    .cv-status-ok {
        background: #dcfce7;
        color: #166534;
    }

    .cv-status-ok::before {
        background: #22c55e;
    }

    .cv-status-review {
        background: #fef3c7;
        color: #92400e;
    }

    .cv-status-review::before {
        background: #f59e0b;
    }

    .cv-top-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: minmax(280px, 1.4fr) minmax(300px, 1fr);
        margin-top: 12px;
    }

    .cv-panel {
        border: 1px solid var(--cv-line);
        border-radius: 14px;
        padding: 14px 16px;
    }

    .cv-panel-label {
        color: var(--cv-muted);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .03em;
        text-transform: uppercase;
    }

    .cv-weight-row {
        align-items: flex-end;
        display: flex;
        gap: 12px;
        justify-content: space-between;
        margin-top: 5px;
    }

    .cv-weight-value {
        font-size: 24px;
        font-weight: 800;
        letter-spacing: -.02em;
    }

    .cv-weight-limit {
        color: var(--cv-muted);
        font-size: 14px;
        font-weight: 650;
    }

    .cv-weight-remaining {
        color: #166534;
        font-size: 13px;
        font-weight: 750;
        text-align: right;
    }

    .cv-weight-over {
        color: #b91c1c;
    }

    .cv-progress {
        background: #e8ecf2;
        border-radius: 99px;
        height: 9px;
        margin-top: 11px;
        overflow: hidden;
    }

    .cv-progress-bar {
        background: #22a45a;
        border-radius: inherit;
        height: 100%;
    }

    .cv-progress-bar-over {
        background: #dc2626;
    }

    .cv-metrics {
        display: grid;
        gap: 8px;
        grid-template-columns: repeat(4, 1fr);
    }

    .cv-metric {
        background: var(--cv-soft);
        border-radius: 10px;
        min-width: 0;
        padding: 9px 10px;
    }

    .cv-metric-value {
        display: block;
        font-size: 19px;
        font-weight: 800;
    }

    .cv-metric-label {
        color: var(--cv-muted);
        display: block;
        font-size: 11px;
        margin-top: 1px;
    }

    .cv-fill-panel {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 14px;
        margin-top: 12px;
        padding: 13px 15px;
    }

    .cv-fill-heading {
        align-items: baseline;
        display: flex;
        flex-wrap: wrap;
        gap: 5px 12px;
        justify-content: space-between;
    }

    .cv-fill-title {
        color: #1e3a8a;
        font-size: 14px;
        font-weight: 850;
    }

    .cv-fill-note {
        color: #4b6290;
        font-size: 11px;
    }

    .cv-fill-grid {
        display: grid;
        gap: 8px;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        margin-top: 9px;
    }

    .cv-fill-item {
        background: rgba(255, 255, 255, .78);
        border: 1px solid #dbeafe;
        border-radius: 10px;
        padding: 9px 10px;
    }

    .cv-fill-item-top {
        align-items: center;
        display: flex;
        gap: 8px;
        justify-content: space-between;
    }

    .cv-fill-product {
        font-weight: 800;
    }

    .cv-fill-quantity {
        color: #1d4ed8;
        font-size: 18px;
        font-weight: 900;
        white-space: nowrap;
    }

    .cv-fill-meta {
        color: #52627d;
        font-size: 11px;
        margin-top: 3px;
    }

    .cv-section {
        margin-top: 18px;
    }

    .cv-section-heading {
        align-items: baseline;
        display: flex;
        flex-wrap: wrap;
        gap: 6px 12px;
        justify-content: space-between;
        margin-bottom: 8px;
    }

    .cv-section-title {
        font-size: 16px;
        font-weight: 800;
    }

    .cv-section-note {
        color: var(--cv-muted);
        font-size: 12px;
    }

    .cv-diagram-frame {
        background: #fbfcfe;
        border: 1px solid var(--cv-line);
        border-radius: 14px;
        contain: inline-size;
        min-width: 0;
        overflow: hidden;
        padding: 16px 16px 10px;
        width: 100%;
    }

    .cv-truck {
        min-width: 0;
    }

    .cv-truck-scroll {
        contain: inline-size;
        min-width: 0;
        overflow-x: auto;
        padding-bottom: 8px;
        width: 100%;
        -webkit-overflow-scrolling: touch;
    }

    .cv-truck-canvas {
        min-width: 980px;
    }

    .cv-direction-row {
        color: var(--cv-muted);
        display: flex;
        font-size: 11px;
        font-weight: 800;
        justify-content: space-between;
        letter-spacing: .06em;
        margin: 0 8px 5px 8px;
        text-transform: uppercase;
    }

    .cv-truck-body {
        align-items: end;
        display: grid;
        gap: 0;
        grid-template-columns: 138px minmax(0, 1fr);
    }

    .cv-tractor {
        color: #344054;
        height: 122px;
        margin-bottom: 6px;
        width: 138px;
    }

    .cv-tractor-wheel {
        fill: var(--cv-diagram-bg);
        stroke: currentColor;
        stroke-width: 7;
    }

    .cv-trailer-assembly {
        align-items: end;
        display: flex;
        min-width: 0;
    }

    .cv-trailer-stack {
        flex: 1 0 max-content;
        min-width: max-content;
    }

    .cv-piggyback-wrap {
        align-items: center;
        color: #344054;
        display: flex;
        flex: 0 0 76px;
        flex-direction: column;
        margin: 0 0 1px -16px;
        pointer-events: none;
        position: relative;
        z-index: 2;
    }

    .cv-piggyback {
        height: 92px;
        width: 74px;
    }

    .cv-piggyback-label {
        color: var(--cv-muted);
        font-size: 8px;
        font-weight: 800;
        letter-spacing: .04em;
        margin-top: -2px;
        text-transform: uppercase;
    }

    .cv-trailer {
        border-bottom: 7px solid #344054;
        min-width: 700px;
        padding: 0 5px;
        position: relative;
    }

    .cv-trailer-cargo {
        --cv-rack-level-height: 40px;
        align-items: end;
        display: flex;
        gap: 10px;
    }

    .cv-rack-grid {
        align-items: end;
        display: grid;
        flex: 1;
        gap: 5px;
    }

    .cv-flatbed-zone {
        min-width: 0;
        position: relative;
    }

    .cv-flatbed-zone-label {
        color: var(--cv-muted);
        font-size: 8px;
        font-weight: 800;
        letter-spacing: .04em;
        left: 0;
        position: absolute;
        text-align: center;
        top: -15px;
        text-transform: uppercase;
        width: 100%;
    }

    .cv-flatbed-slots {
        display: grid;
        gap: 4px;
    }

    .cv-flatbed-slot {
        align-items: center;
        border: 2px solid #344054;
        display: flex;
        flex-direction: column;
        height: calc((var(--cv-rack-level-height) * 3) + 6px);
        justify-content: center;
        min-width: 66px;
        padding: 3px;
        position: relative;
        text-align: center;
    }

    .cv-flatbed-position {
        min-width: 66px;
    }

    .cv-flatbed-slot-empty {
        border-style: dashed;
        color: #98a2b3;
        font-size: 10px;
        font-weight: 700;
    }

    .cv-flatbed-spot-label {
        color: var(--cv-muted);
        font-size: 10px;
        font-weight: 800;
        line-height: 1.15;
        margin-top: 5px;
        text-align: center;
    }

    .cv-rack {
        min-width: 76px;
        position: relative;
    }

    .cv-rack-body {
        background: #fff;
        border: 3px solid #344054;
        display: grid;
        overflow: hidden;
    }

    .cv-rack-open {
        align-items: center;
        border: 2px dashed #b8c0ce;
        color: #98a2b3;
        display: flex;
        font-size: 11px;
        font-weight: 700;
        height: calc((var(--cv-rack-level-height) * 3) + 6px);
        justify-content: center;
        text-align: center;
    }

    .cv-rack-cell {
        align-items: center;
        border-bottom: 2px solid #344054;
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-height: 0;
        padding: 2px;
        position: relative;
        text-align: center;
    }

    .cv-rack-cell:last-child {
        border-bottom: 0;
    }

    .cv-rack-cell-empty {
        background: repeating-linear-gradient(135deg, #fff, #fff 7px, #f2f4f7 7px, #f2f4f7 14px);
        color: #98a2b3;
    }

    .cv-cell-code {
        font-size: 15px;
        font-weight: 900;
        line-height: 1;
    }

    .cv-cell-code-pallet {
        font-size: 10px;
        line-height: 1.15;
    }

    .cv-cell-meta {
        font-size: 8px;
        font-weight: 750;
        line-height: 1.1;
        margin-top: 2px;
        text-transform: uppercase;
    }

    .cv-cell-stop-badge {
        background: rgba(255, 255, 255, .62);
        border: 1px solid rgba(71, 84, 103, .18);
        border-radius: 3px;
        color: #667085;
        font-size: 7px;
        font-weight: 750;
        line-height: 1;
        padding: 2px 3px;
        position: absolute;
        right: 2px;
        top: 2px;
    }

    .cv-stop-1 {
        background: #dbeafe;
        color: #1e40af;
    }

    .cv-stop-2 {
        background: #dcfce7;
        color: #166534;
    }

    .cv-stop-3 {
        background: #fef3c7;
        color: #92400e;
    }

    .cv-stop-4 {
        background: #f3e8ff;
        color: #6b21a8;
    }

    .cv-stop-5 {
        background: #ffe4e6;
        color: #9f1239;
    }

    .cv-stop-6 {
        background: #cffafe;
        color: #155e75;
    }

    .cv-rack-label {
        color: #475467;
        font-size: 10px;
        font-weight: 800;
        line-height: 1.15;
        margin-top: 5px;
        text-align: center;
    }

    .cv-rack-weight {
        color: var(--cv-muted);
        display: block;
        font-size: 9px;
        font-weight: 700;
        margin-top: 2px;
    }

    .cv-trailer-wheels {
        display: flex;
        gap: 4px;
        justify-content: flex-end;
        margin-right: 8%;
        margin-top: -2px;
    }

    .cv-wheel {
        background: #344054;
        border: 4px solid #344054;
        border-radius: 50%;
        box-shadow: inset 0 0 0 8px #fbfcfe;
        height: 42px;
        width: 42px;
    }

    .cv-legends {
        display: grid;
        gap: 12px;
        grid-template-columns: 1fr 1fr;
        margin-top: 10px;
    }

    .cv-legend-box {
        background: var(--cv-soft);
        border-radius: 10px;
        padding: 10px 12px;
    }

    .cv-legend-title {
        color: var(--cv-muted);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .04em;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .cv-legend-list {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 14px;
    }

    .cv-legend-item {
        align-items: center;
        display: inline-flex;
        font-size: 12px;
        gap: 6px;
    }

    .cv-code-chip {
        background: #344054;
        border-radius: 5px;
        color: white;
        font-size: 11px;
        font-weight: 850;
        min-width: 30px;
        padding: 2px 5px;
        text-align: center;
    }

    .cv-stop-chip {
        border-radius: 5px;
        font-size: 10px;
        font-weight: 850;
        min-width: 27px;
        padding: 2px 5px;
        text-align: center;
    }

    .cv-alert {
        border-radius: 12px;
        margin-top: 12px;
        padding: 12px 14px;
    }

    .cv-alert-title {
        font-size: 13px;
        font-weight: 850;
    }

    .cv-alert ul {
        margin: 6px 0 0 18px;
        padding: 0;
    }

    .cv-alert li {
        margin-top: 3px;
    }

    .cv-alert-danger {
        background: #fff1f2;
        border: 1px solid #fecdd3;
        color: #9f1239;
    }

    .cv-alert-warning {
        background: #fffbeb;
        border: 1px solid #fde68a;
        color: #92400e;
    }

    .cv-table-scroll {
        contain: inline-size;
        min-width: 0;
        overflow-x: auto;
        width: 100%;
        -webkit-overflow-scrolling: touch;
    }

    .cv-unplaced-table,
    .cv-detail-table {
        border-collapse: collapse;
        margin-top: 8px;
        width: 100%;
    }

    .cv-unplaced-table th,
    .cv-unplaced-table td,
    .cv-detail-table th,
    .cv-detail-table td {
        border-top: 1px solid rgba(127, 29, 29, .13);
        padding: 7px 8px;
        text-align: left;
        vertical-align: top;
    }

    .cv-unplaced-table th,
    .cv-detail-table th {
        font-size: 10px;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .cv-detail-table td {
        border-color: var(--cv-line);
    }

    .cv-num {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .cv-details {
        border: 1px solid var(--cv-line);
        border-radius: 12px;
        margin-top: 16px;
        overflow: hidden;
    }

    .cv-details summary {
        background: var(--cv-soft);
        cursor: pointer;
        font-weight: 750;
        list-style-position: inside;
        padding: 11px 13px;
    }

    .cv-detail-stop {
        padding: 12px 14px;
    }

    .cv-detail-stop+.cv-detail-stop {
        border-top: 1px solid var(--cv-line);
    }

    .cv-detail-stop-title {
        font-weight: 800;
    }

    .cv-detail-stop-meta {
        color: var(--cv-muted);
        font-size: 12px;
        margin-top: 2px;
    }

    html.dark .cv-load-sheet {
        --cv-ink: #f1f5f9;
        --cv-muted: #a8b0bf;
        --cv-line: #3a4353;
        --cv-soft: #232a36;
        --cv-diagram-bg: #171d26;
    }

    html.dark .cv-sheet-header {
        background: #202733;
    }

    html.dark .cv-diagram-frame {
        background: #171d26;
    }

    html.dark .cv-rack-body {
        background: #202733;
        border-color: #aeb7c5;
    }

    html.dark .cv-flatbed-slot {
        border-color: #aeb7c5;
    }

    html.dark .cv-rack-cell {
        border-color: #aeb7c5;
    }

    html.dark .cv-rack-cell-empty {
        background: #202733;
    }

    html.dark .cv-cell-stop-badge {
        background: rgba(17, 24, 39, .5);
        border-color: rgba(229, 231, 235, .2);
        color: #d1d5db;
    }

    html.dark .cv-trailer {
        border-color: #aeb7c5;
    }

    html.dark .cv-tractor {
        color: #aeb7c5;
    }

    html.dark .cv-piggyback-wrap {
        color: #aeb7c5;
    }

    html.dark .cv-wheel {
        background: #aeb7c5;
        border-color: #aeb7c5;
        box-shadow: inset 0 0 0 8px #171d26;
    }

    html.dark .cv-fill-panel {
        background: #172554;
        border-color: #1e40af;
    }

    html.dark .cv-fill-title,
    html.dark .cv-fill-quantity {
        color: #93c5fd;
    }

    html.dark .cv-fill-note,
    html.dark .cv-fill-meta {
        color: #bfdbfe;
    }

    html.dark .cv-fill-item {
        background: rgba(30, 58, 138, .28);
        border-color: #1e40af;
    }

    @media (max-width: 820px) {

        .cv-sheet-header,
        .cv-weight-row {
            align-items: flex-start;
            flex-direction: column;
        }

        .cv-top-grid,
        .cv-legends {
            grid-template-columns: 1fr;
        }

        .cv-metrics {
            grid-template-columns: repeat(2, 1fr);
        }

        .cv-weight-remaining {
            text-align: left;
        }

        .cv-truck-canvas {
            min-width: 760px;
        }

        .cv-truck-body {
            grid-template-columns: 108px minmax(0, 1fr);
        }

        .cv-tractor {
            height: 100px;
            width: 108px;
        }

        .cv-piggyback-wrap {
            flex-basis: 62px;
            margin-left: -5px;
        }

        .cv-piggyback {
            height: 78px;
            width: 60px;
        }

        .cv-trailer {
            min-width: 590px;
        }

        .cv-trailer-cargo {
            --cv-rack-level-height: 36px;
        }

        .cv-rack {
            min-width: 66px;
        }

        .cv-cell-code {
            font-size: 13px;
        }
    }

    @media print {
        .cv-load-sheet {
            color: #111827;
        }

        .cv-diagram-frame {
            overflow: visible;
        }

        .cv-truck-scroll {
            overflow: visible;
        }

        .cv-details {
            display: none;
        }
    }
</style>

<div class="cv-load-sheet">
    <header class="cv-sheet-header">
        <div>
            <div class="cv-vehicle-name">{{ $vehicle['name'] ?? 'Vehicle configuration missing' }}</div>
            <div class="cv-vehicle-meta">
                @if ($vehicle)
                    {{ $vehicle['rack_spot_count'] ?? 'No' }} physical racks
                    · {{ number_format($vehicle['flatbed_pallet_capacity'] ?? 0) }} fallback flatbed pallet spots
                    · Piggyback forklift {{ $vehicle['piggyback_forklift_onboard'] ? 'onboard' : 'already at site' }}
                @else
                    Select a vehicle on the trip to generate a loading diagram.
                @endif
            </div>
        </div>

        <span class="cv-status {{ $needsReview ? 'cv-status-review' : 'cv-status-ok' }}">
            {{ $needsReview ? 'Manual review needed' : 'Cheat sheet ready' }}
        </span>
    </header>

    <div class="cv-top-grid">
        <section class="cv-panel">
            <div class="cv-panel-label">Product cargo weight</div>
            <div class="cv-weight-row">
                <div class="cv-weight-value">
                    {{ number_format($knownWeight, 0) }} lb
                    @if ($maximumWeight > 0)
                        <span class="cv-weight-limit">/ {{ number_format($maximumWeight, 0) }} max</span>
                    @endif
                </div>

                @if ($maximumWeight > 0)
                    <div class="cv-weight-remaining {{ $summary['is_overweight'] ? 'cv-weight-over' : '' }}">
                        @if ($summary['is_overweight'])
                            {{ number_format($summary['overweight_by_lbs'], 0) }} lb over limit
                        @else
                            {{ number_format($summary['remaining_product_weight_lbs'], 0) }} lb remaining
                        @endif
                    </div>
                @endif
            </div>

            @if ($maximumWeight > 0)
                <div class="cv-progress" aria-label="Product weight capacity used">
                    <div class="cv-progress-bar {{ $summary['is_overweight'] ? 'cv-progress-bar-over' : '' }}"
                        style="width: {{ number_format($weightPercent, 2, '.', '') }}%"></div>
                </div>
            @endif
        </section>

        <section class="cv-panel cv-metrics">
            <div class="cv-metric">
                <span class="cv-metric-value">{{ number_format($summary['product_units']) }}</span>
                <span class="cv-metric-label">Product units</span>
            </div>
            <div class="cv-metric">
                <span class="cv-metric-value">{{ number_format($diagram['rack_spot_count'] ?? 0) }}</span>
                <span class="cv-metric-label">
                    Trailer racks · {{ number_format($diagram['used_rack_spots'] ?? 0) }} carrying product
                </span>
            </div>
            <div class="cv-metric">
                <span class="cv-metric-value">{{ number_format($summary['oversized_rack_spots']) }}</span>
                <span class="cv-metric-label">Single racks</span>
            </div>
            <div class="cv-metric">
                <span class="cv-metric-value">{{ number_format($summary['pallets']) }}</span>
                <span class="cv-metric-label">
                    Pallets · {{ number_format($diagram['flatbed_pallets_used'] ?? 0) }} strapped to flatbed
                </span>
            </div>
        </section>
    </div>

    @if ($fillAllocations->isNotEmpty())
        <section class="cv-fill-panel">
            <div class="cv-fill-heading">
                <div class="cv-fill-title">Fill load allocation</div>
                <div class="cv-fill-note">
                    Fixed products load first. Lower priority numbers receive remaining safe capacity first.
                </div>
            </div>
            <div class="cv-fill-grid">
                @foreach ($fillAllocations as $allocation)
                    <div class="cv-fill-item">
                        <div class="cv-fill-item-top">
                            <span class="cv-fill-product">{{ $allocation['sku'] }}</span>
                            <span class="cv-fill-quantity">
                                {{ $allocation['resolved'] ? number_format($allocation['planned_quantity']) : '?' }}
                                planned
                            </span>
                        </div>
                        <div class="cv-fill-meta">
                            Priority {{ $allocation['priority'] }} · Stop {{ $allocation['stop_sequence'] }} ·
                            {{ $allocation['order_number'] }}
                            ·
                            {{ match ($allocation['source']) {
                                'locked' => 'Locked at dispatch',
                                'manual' => 'Manual allocation',
                                'automatic' => 'Calculated automatically',
                                default => 'Needs review',
                            } }}
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="cv-section">
        <div class="cv-section-heading">
            <h2 class="cv-section-title">Truck loading diagram</h2>

        </div>

        @if ($diagram['available'])
            <div class="cv-diagram-frame">
                <div class="cv-truck">
                    <div class="cv-truck-scroll">
                        <div class="cv-truck-canvas">
                            <div class="cv-direction-row">
                                <span>Front / tractor</span>
                                <span>Rear / unload first →</span>
                            </div>
                            <div class="cv-truck-body">
                                <svg class="cv-tractor" viewBox="0 0 160 140" role="img"
                                    aria-label="Compact cab-over truck tractor">
                            <path d="M8 43 19 24h75l14 18v57h43v10H8z" fill="currentColor" opacity=".14" />
                            <path
                                d="M8 103V43l11-19h75l14 18v57h43v10H8zM22 32h62l14 16v22H20V43zM99 48v51M108 87h23l9 8M13 78h28M13 87h28M53 82h34M52 94h39M14 101h23"
                                fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="4.5" />
                            <path d="M96 98h23v10H92zM126 94h17l7 6h-24z" fill="currentColor" opacity=".35" />
                            <circle class="cv-tractor-wheel" cx="43" cy="108" r="17" />
                            <circle class="cv-tractor-wheel" cx="119" cy="108" r="17" />
                                </svg>

                                <div class="cv-trailer-assembly">
                            <div class="cv-trailer-stack">
                                <div class="cv-trailer">
                                    <div class="cv-trailer-cargo">
                                    <div class="cv-rack-grid"
                                        style="grid-template-columns: repeat({{ count($diagram['racks']) }}, minmax(76px, 1fr));">
                                        @foreach ($diagram['racks'] as $rack)
                                            <div class="cv-rack">
                                                @if ($rack['type_code'])
                                                    <div class="cv-rack-body"
                                                        style="grid-template-rows: repeat({{ $rack['level_count'] }}, var(--cv-rack-level-height));"
                                                        title="Rack {{ $rack['number'] }} · {{ $rack['type_label'] }}">
                                                        @foreach (array_reverse($rack['cells'], true) as $cell)
                                                            @if ($cell)
                                                                <div
                                                                    class="cv-rack-cell cv-stop-{{ (($cell['stop_sequence'] - 1) % 6) + 1 }}">
                                                                    @if ($isMultiStop)
                                                                        <span class="cv-cell-stop-badge">S{{ $cell['stop_sequence'] }}</span>
                                                                    @endif
                                                                    <span
                                                                        class="cv-cell-code {{ $cell['is_pallet_level'] ?? false ? 'cv-cell-code-pallet' : '' }}">{{ $cell['code'] }}</span>
                                                                    @if (($cell['is_pallet_level'] ?? false) || ($cell['component'] ?? null) === 'half')
                                                                        <span class="cv-cell-meta">
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
                                                                <div class="cv-rack-cell cv-rack-cell-empty">—</div>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="cv-rack-open">Empty<br>rack</div>
                                                @endif
                                                <div class="cv-rack-label">
                                                    R{{ $rack['number'] }}
                                                    @if ($rack['type_code'])
                                                        <span class="cv-rack-weight">
                                                            {{ number_format($rack['product_weight_lbs'], 0) }}
                                                            lb{{ $rack['has_unknown_weight'] ? ' + ?' : '' }}
                                                        </span>
                                                    @else
                                                        <span class="cv-rack-weight">0 lb</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    @if (($diagram['flatbed_pallets_used'] ?? 0) > 0)
                                        <div class="cv-flatbed-zone">
                                            <div class="cv-flatbed-zone-label">Strapped flatbed fallback</div>
                                            <div class="cv-flatbed-slots"
                                                style="grid-template-columns: repeat({{ $diagram['flatbed_pallet_capacity'] }}, minmax(66px, 1fr));">
                                                @for ($spot = 1; $spot <= $diagram['flatbed_pallet_capacity']; $spot++)
                                                    @php
                                                        $pallet = $diagram['flatbed_pallets'][$spot - 1] ?? null;
                                                    @endphp
                                                    <div class="cv-flatbed-position">
                                                        @if ($pallet)
                                                            <div class="cv-flatbed-slot cv-stop-{{ (($pallet['stop_sequence'] - 1) % 6) + 1 }}"
                                                                title="Flatbed pallet {{ $spot }} · {{ $pallet['name'] }}">
                                                                @if ($isMultiStop)
                                                                    <span class="cv-cell-stop-badge">S{{ $pallet['stop_sequence'] }}</span>
                                                                @endif
                                                                <span class="cv-cell-code cv-cell-code-pallet">{{ $pallet['code'] }}</span>
                                                                <span class="cv-cell-meta">Strap to deck</span>
                                                            </div>
                                                        @else
                                                            <div class="cv-flatbed-slot cv-flatbed-slot-empty">Open</div>
                                                        @endif
                                                        <div class="cv-flatbed-spot-label">
                                                            P{{ $spot }}
                                                            @if ($pallet)
                                                                <span class="cv-rack-weight">
                                                                    {{ $pallet['total_weight_lbs'] === null ? '?' : number_format($pallet['total_weight_lbs'], 0) }} lb
                                                                </span>
                                                            @else
                                                                <span class="cv-rack-weight">&nbsp;</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endfor
                                            </div>
                                        </div>
                                    @endif
                                    </div>
                                </div>
                                <div class="cv-trailer-wheels" aria-hidden="true">
                                    <span class="cv-wheel"></span>
                                    <span class="cv-wheel"></span>
                                </div>
                            </div>

                            @if ($vehicle['piggyback_forklift_onboard'] ?? false)
                                <div class="cv-piggyback-wrap">
                                    <svg class="cv-piggyback" viewBox="0 0 82 100" role="img"
                                        aria-label="Piggyback forklift suspended from rear of trailer">
                                        <path d="M24 18v65M31 22v57M24 48H2M24 56H2" fill="none"
                                            stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                            stroke-width="4" />
                                        <path d="M32 39h29l10 15v25H32z" fill="currentColor" opacity=".14" />
                                        <path d="M34 39h27l10 15v25H32V51zM43 39V24h19l8 15M43 25v27h27M34 61h15M72 61h7v18h-8"
                                            fill="none" stroke="currentColor" stroke-linecap="round"
                                            stroke-linejoin="round" stroke-width="4" />
                                        <circle class="cv-tractor-wheel" cx="42" cy="81" r="10" />
                                        <circle class="cv-tractor-wheel" cx="68" cy="81" r="10" />
                                        <path d="M20 46h8v14h-8z" fill="currentColor" opacity=".35" />
                                    </svg>
                                    <span class="cv-piggyback-label">Piggyback</span>
                                </div>
                            @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="cv-legends">
                        <div class="cv-legend-box">
                            <div class="cv-legend-title">Product codes</div>
                            <div class="cv-legend-list">
                                @forelse ($diagram['legend'] as $entry)
                                    <span class="cv-legend-item">
                                        <span class="cv-code-chip">{{ $entry['code'] }}</span>
                                        <span><strong>{{ $entry['sku'] }}</strong> · {{ $entry['name'] }}</span>
                                    </span>
                                    @if (($entry['units_per_rack_position'] ?? 1) > 1)
                                        <span class="cv-legend-item">
                                            <span class="cv-code-chip">{{ $entry['units_per_rack_position'] }}×</span>
                                            <span>Up to {{ $entry['units_per_rack_position'] }} {{ $entry['sku'] }}
                                                products share one rack position</span>
                                        </span>
                                    @endif
                                    @if (($entry['placement_strategy'] ?? null) === \App\Models\LoadingProfile::PLACEMENT_FULL_TOP_SPLIT_BOTTOM_PAIR)
                                        <span class="cv-legend-item">
                                            <span class="cv-code-chip">½{{ $entry['code'] }}</span>
                                            <span>One half of a split {{ $entry['sku'] }} · two halves count as one
                                                product</span>
                                        </span>
                                    @endif
                                @empty
                                    <span class="cv-section-note">No products were placed.</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="cv-legend-box">
                            <div class="cv-legend-title">Delivery stops</div>
                            <div class="cv-legend-list">
                                @foreach ($result['stops'] as $stop)
                                    <span class="cv-legend-item">
                                        <span
                                            class="cv-stop-chip cv-stop-{{ (($stop['sequence'] - 1) % 6) + 1 }}">S{{ $stop['sequence'] }}</span>
                                        <span>{{ $stop['location_name'] ?: 'Location unavailable' }} ·
                                            {{ $stop['order_number'] ?: 'Order ' . $stop['order_id'] }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="cv-alert cv-alert-warning">
                <div class="cv-alert-title">Diagram unavailable</div>
                <p>{{ $diagram['message'] }}</p>
            </div>
        @endif
    </section>

    @if ($diagram['unplaced'])
        <section class="cv-alert cv-alert-danger">
            <div class="cv-alert-title">Not shown on the truck — manual placement required</div>
            <div class="cv-table-scroll">
                <table class="cv-unplaced-table">
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
                                <td class="cv-num">{{ number_format($item['quantity']) }}</td>
                                <td class="cv-num">{{ $item['stop_sequence'] }}</td>
                                <td><strong>{{ $item['sku'] }}</strong><br>{{ $item['name'] }}</td>
                                <td>{{ $item['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($warningMessages->isNotEmpty())
        <section class="cv-alert cv-alert-warning">
            <div class="cv-alert-title">Load checks</div>
            <ul>
                @foreach ($warningMessages as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <details class="cv-details">
        <summary>Order details and weights</summary>
        @forelse ($result['stops'] as $stop)
            <section class="cv-detail-stop">
                <div class="cv-detail-stop-title">Stop {{ $stop['sequence'] }} ·
                    {{ $stop['location_name'] ?: 'Location unavailable' }}</div>
                <div class="cv-detail-stop-meta">
                    {{ $stop['order_number'] ?: 'Order ' . $stop['order_id'] }}
                    · {{ number_format($stop['summary']['known_weight_lbs'], 0) }} lb known
                    ·
                    {{ $stop['sequence'] === 1 ? 'Load toward rear — unload first' : 'Load forward of earlier stops' }}
                </div>
                <div class="cv-table-scroll">
                    <table class="cv-detail-table">
                        <thead>
                            <tr>
                                <th>Qty</th>
                                <th>Product</th>
                                <th>Loading requirement</th>
                                <th>Weight</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stop['items'] as $item)
                                <tr>
                                    <td class="cv-num">
                                        @if ($item['fill_load'])
                                            Fill →
                                            {{ $item['fill_resolved'] ? number_format($item['quantity']) : '?' }}
                                        @else
                                            {{ number_format($item['quantity'] ?? 0) }}
                                        @endif
                                    </td>
                                    <td><strong>{{ $item['sku'] }}</strong><br>{{ $item['name'] }}</td>
                                    <td>
                                        @if ($item['handling_method'] === 'pallet')
                                            {{ number_format($item['pallet_equivalent'] ?? 0, 2) }} pallet equivalent
                                        @elseif ($item['rack_requirement'] === 'single')
                                            Oversized single rack
                                        @elseif ($item['rack_requirement'] === 'standard')
                                            {{ $item['required_rack_type'] ? str($item['required_rack_type'])->replace('_', ' ')->title() : 'Rack type not confirmed' }}
                                            @php
                                                $alternateRackTypes = collect($item['allowed_rack_type_codes'] ?? [])
                                                    ->reject(
                                                        fn(string $code): bool => $code === $item['required_rack_type'],
                                                    )
                                                    ->map(fn(string $code) => str($code)->replace('_', ' ')->title())
                                                    ->values();
                                            @endphp
                                            @if ($alternateRackTypes->isNotEmpty())
                                                · also fits {{ $alternateRackTypes->join(', ') }}
                                            @endif
                                            @if (($item['units_per_rack_position'] ?? 1) > 1)
                                                · {{ $item['units_per_rack_position'] }} per rack position
                                            @endif
                                            @if ($item['required_rack_level'] === 'bottom')
                                                · bottom only
                                            @endif
                                        @else
                                            Manual review
                                        @endif
                                    </td>
                                    <td class="cv-num">
                                        {{ $item['total_weight_lbs'] === null ? 'Unknown' : number_format($item['total_weight_lbs'], 0) . ' lb' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <section class="cv-detail-stop">This trip does not have any delivery stops.</section>
        @endforelse
    </details>
</div>
