@php
    use App\Models\Order;
    use App\Services\LoadPlanning\DraftOrderLoadPreviewService;

    $preview = app(DraftOrderLoadPreviewService::class)->forForm(
        $record instanceof Order ? $record : null,
        [
            'location_id' => $get('location_id'),
            'order_date' => $get('order_date'),
            'vehicle_configuration_id' => $get('load_preview_vehicle_configuration_id'),
            'orderProducts' => $get('orderProducts') ?? [],
        ],
    );
    $diagram = $preview['diagram'];
    $racks = collect($diagram['racks'] ?? []);
    $flatbedSpots = collect($diagram['flatbed_pallets'] ?? [])->keyBy('spot_number');
    $draftStopSequence = $preview['context']['draft_stop_sequence'];
    $maximumRackLevels = max(1, (int) $racks->max('level_count'));
    $percent = static fn (float|int $value, float|int|null $maximum): float => $maximum
        ? min(100, max(0, ($value / $maximum) * 100))
        : 0;
    $weightMaximum = $preview['weight']['maximum'];
    $weightExistingPercent = $percent($preview['weight']['existing'], $weightMaximum);
    $weightDraftPercent = min(
        100 - $weightExistingPercent,
        $percent($preview['weight']['draft'], $weightMaximum),
    );
    $rackExistingPercent = $percent($preview['racks']['existing_used'], $preview['racks']['capacity']);
    $rackDraftPercent = min(
        100 - $rackExistingPercent,
        $percent($preview['racks']['draft_added'], $preview['racks']['capacity']),
    );
    $flatbedExistingPercent = $percent($preview['flatbed']['existing_used'], $preview['flatbed']['capacity']);
    $flatbedDraftPercent = min(
        100 - $flatbedExistingPercent,
        $percent($preview['flatbed']['draft_added'], $preview['flatbed']['capacity']),
    );
@endphp

@once
    <style>
        .cv-load-preview {
            position: sticky;
            top: 1rem;
            overflow: hidden;
            border: 1px solid rgb(209 213 219);
            border-radius: .9rem;
            background: white;
            box-shadow: 0 1px 2px rgb(15 23 42 / .04);
        }

        .dark .cv-load-preview {
            border-color: rgb(55 65 81);
            background: rgb(17 24 39);
        }

        .cv-load-preview__header,
        .cv-load-preview__body {
            padding: 1rem;
        }

        .cv-load-preview__header {
            border-bottom: 1px solid rgb(229 231 235);
            background: rgb(248 250 252);
        }

        .dark .cv-load-preview__header {
            border-color: rgb(55 65 81);
            background: rgb(31 41 55);
        }

        .cv-load-preview__title-row,
        .cv-load-preview__capacity-heading,
        .cv-load-preview__impact-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
        }

        .cv-load-preview__title {
            margin: 0;
            color: rgb(15 23 42);
            font-size: 1.1rem;
            font-weight: 700;
            line-height: 1.3;
        }

        .dark .cv-load-preview__title,
        .dark .cv-load-preview__metric-value,
        .dark .cv-load-preview__impact-value {
            color: rgb(248 250 252);
        }

        .cv-load-preview__badge {
            flex: none;
            border: 1px solid currentColor;
            border-radius: 999px;
            padding: .2rem .5rem;
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .035em;
            line-height: 1;
            text-transform: uppercase;
        }

        .cv-load-preview__badge--fits {
            color: rgb(21 128 61);
            background: rgb(240 253 244);
        }

        .cv-load-preview__badge--does_not_fit {
            color: rgb(185 28 28);
            background: rgb(254 242 242);
        }

        .cv-load-preview__badge--review,
        .cv-load-preview__badge--weight_only {
            color: rgb(180 83 9);
            background: rgb(255 251 235);
        }

        .cv-load-preview__badge--empty {
            color: rgb(75 85 99);
            background: rgb(249 250 251);
        }

        .cv-load-preview__eyebrow,
        .cv-load-preview__context,
        .cv-load-preview__muted,
        .cv-load-preview__legend,
        .cv-load-preview__note {
            color: rgb(100 116 139);
            font-size: .75rem;
            line-height: 1.45;
        }

        .cv-load-preview__context {
            margin-top: .45rem;
        }

        .cv-load-preview__context strong {
            color: rgb(51 65 85);
            font-weight: 650;
        }

        .dark .cv-load-preview__context strong {
            color: rgb(226 232 240);
        }

        .cv-load-preview__truck-select {
            margin-bottom: 1rem;
        }

        .cv-load-preview__capacities {
            display: grid;
            gap: .8rem;
        }

        .cv-load-preview__metric-label,
        .cv-load-preview__metric-value {
            color: rgb(51 65 85);
            font-size: .78rem;
            font-weight: 650;
        }

        .cv-load-preview__capacity-bar {
            display: flex;
            height: .58rem;
            margin-top: .35rem;
            overflow: hidden;
            border: 1px solid rgb(203 213 225);
            border-radius: 999px;
            background: rgb(248 250 252);
        }

        .cv-load-preview__capacity-existing {
            background: rgb(100 116 139);
        }

        .cv-load-preview__capacity-draft {
            background: rgb(37 99 235);
        }

        .cv-load-preview__legend {
            display: flex;
            flex-wrap: wrap;
            gap: .7rem;
            margin-top: .65rem;
        }

        .cv-load-preview__legend-item {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
        }

        .cv-load-preview__swatch {
            width: .65rem;
            height: .65rem;
            border: 1px solid rgb(148 163 184);
            border-radius: .15rem;
        }

        .cv-load-preview__swatch--existing {
            background: rgb(100 116 139);
        }

        .cv-load-preview__swatch--draft {
            border-color: rgb(37 99 235);
            background: rgb(37 99 235);
        }

        .cv-load-preview__swatch--remaining {
            background: white;
        }

        .cv-load-preview__diagram {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgb(226 232 240);
        }

        .dark .cv-load-preview__diagram {
            border-color: rgb(55 65 81);
        }

        .cv-load-preview__diagram-title {
            color: rgb(71 85 105);
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .055em;
            text-transform: uppercase;
        }

        .cv-load-preview__rack-strip {
            display: grid;
            grid-template-columns: repeat(var(--rack-count), minmax(2rem, 1fr));
            gap: .28rem;
            margin-top: .55rem;
        }

        .cv-load-preview__rack {
            display: flex;
            min-width: 0;
            flex-direction: column;
            gap: .16rem;
        }

        .cv-load-preview__rack-cells {
            display: grid;
            flex: 1;
            grid-template-rows: repeat(var(--rack-levels), minmax(1.8rem, 1fr));
            gap: 2px;
            min-height: 6.25rem;
        }

        .cv-load-preview__rack-cell,
        .cv-load-preview__flatbed-cell {
            display: flex;
            min-width: 0;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid rgb(148 163 184);
            border-radius: .22rem;
            color: rgb(51 65 85);
            background: rgb(248 250 252);
            font-size: .65rem;
            font-weight: 800;
            text-align: center;
        }

        .cv-load-preview__rack-cell--existing,
        .cv-load-preview__flatbed-cell--existing {
            border-color: rgb(100 116 139);
            color: white;
            background: rgb(100 116 139);
        }

        .cv-load-preview__rack-cell--draft,
        .cv-load-preview__flatbed-cell--draft {
            border-color: rgb(37 99 235);
            color: white;
            background: rgb(37 99 235);
        }

        .cv-load-preview__rack-cell--open,
        .cv-load-preview__flatbed-cell--open {
            border-style: dashed;
            color: rgb(148 163 184);
            background: white;
        }

        .cv-load-preview__rack-cell--open-rack {
            grid-row: 1 / -1;
        }

        .cv-load-preview__rack-cell--spacer {
            visibility: hidden;
        }

        .cv-load-preview__rack-label,
        .cv-load-preview__flatbed-label {
            color: rgb(71 85 105);
            font-size: .64rem;
            font-weight: 750;
            text-align: center;
        }

        .cv-load-preview__flatbed {
            margin-top: .85rem;
        }

        .cv-load-preview__flatbed-strip {
            display: grid;
            grid-template-columns: repeat(var(--flatbed-count), minmax(2.6rem, 1fr));
            gap: .35rem;
            margin-top: .45rem;
        }

        .cv-load-preview__flatbed-cell {
            min-height: 2.8rem;
            padding: .25rem;
        }

        .cv-load-preview__impact {
            display: grid;
            gap: .55rem;
            margin-top: 1rem;
            border: 1px solid rgb(226 232 240);
            border-radius: .65rem;
            padding: .75rem;
            background: rgb(248 250 252);
        }

        .dark .cv-load-preview__impact {
            border-color: rgb(55 65 81);
            background: rgb(31 41 55);
        }

        .cv-load-preview__impact-label {
            color: rgb(71 85 105);
            font-size: .72rem;
            font-weight: 600;
        }

        .cv-load-preview__impact-value {
            color: rgb(30 41 59);
            font-size: .75rem;
            font-weight: 700;
            text-align: right;
        }

        .cv-load-preview__result {
            margin-top: .75rem;
            border: 1px solid currentColor;
            border-radius: .55rem;
            padding: .65rem .75rem;
            font-size: .78rem;
            font-weight: 700;
            line-height: 1.4;
        }

        .cv-load-preview__result--fits {
            color: rgb(21 128 61);
            background: rgb(240 253 244);
        }

        .cv-load-preview__result--does_not_fit {
            color: rgb(185 28 28);
            background: rgb(254 242 242);
        }

        .cv-load-preview__result--review,
        .cv-load-preview__result--weight_only {
            color: rgb(146 64 14);
            background: rgb(255 251 235);
        }

        .cv-load-preview__result--empty {
            color: rgb(75 85 99);
            background: rgb(249 250 251);
        }

        .cv-load-preview__warnings {
            display: grid;
            gap: .3rem;
            margin: .6rem 0 0;
            padding-left: 1.1rem;
            color: rgb(146 64 14);
            font-size: .72rem;
            line-height: 1.4;
        }

        .cv-load-preview__note {
            margin-top: .75rem;
        }

        @media (max-width: 1279px) {
            .cv-load-preview {
                position: static;
            }
        }
    </style>
@endonce

<div class="cv-load-preview">
    <div class="cv-load-preview__header">
        <div class="cv-load-preview__title-row">
            <h2 class="cv-load-preview__title">Truck fit preview</h2>
            <span class="cv-load-preview__badge cv-load-preview__badge--{{ $preview['status'] }}">
                {{ $preview['status_label'] }}
            </span>
        </div>
        <div class="cv-load-preview__eyebrow">Live estimate</div>
        <div class="cv-load-preview__context">
            @if ($preview['context']['trip_number'])
                <strong>{{ $preview['context']['trip_number'] }}</strong>
                · {{ $preview['context']['vehicle_name'] }}
                <br>
                {{ $preview['context']['existing_stops'] }}
                existing {{ \Illuminate\Support\Str::plural('stop', $preview['context']['existing_stops']) }}
                · draft stop {{ $draftStopSequence }}
            @else
                <strong>Standalone order</strong>
                · {{ $preview['context']['vehicle_name'] }}
            @endif
        </div>
    </div>

    <div class="cv-load-preview__body">
        <div class="cv-load-preview__truck-select">
            {{ $getChildSchema() }}
        </div>

        <div class="cv-load-preview__capacities">
            <div>
                <div class="cv-load-preview__capacity-heading">
                    <span class="cv-load-preview__metric-label">Weight</span>
                    <span class="cv-load-preview__metric-value">
                        {{ number_format($preview['weight']['known']) }}
                        @if ($weightMaximum)
                            / {{ number_format($weightMaximum) }} lb
                        @else
                            lb
                        @endif
                    </span>
                </div>
                <div class="cv-load-preview__capacity-bar" aria-label="Truck weight capacity">
                    <span
                        class="cv-load-preview__capacity-existing"
                        style="width: {{ $weightExistingPercent }}%"
                    ></span>
                    <span
                        class="cv-load-preview__capacity-draft"
                        style="width: {{ $weightDraftPercent }}%"
                    ></span>
                </div>
            </div>

            @if ($preview['racks']['capacity'] > 0)
                <div>
                    <div class="cv-load-preview__capacity-heading">
                        <span class="cv-load-preview__metric-label">Racks</span>
                        <span class="cv-load-preview__metric-value">
                            {{ $preview['racks']['used'] }} of {{ $preview['racks']['capacity'] }} used
                        </span>
                    </div>
                    <div class="cv-load-preview__capacity-bar" aria-label="Rack bay capacity">
                        <span
                            class="cv-load-preview__capacity-existing"
                            style="width: {{ $rackExistingPercent }}%"
                        ></span>
                        <span
                            class="cv-load-preview__capacity-draft"
                            style="width: {{ $rackDraftPercent }}%"
                        ></span>
                    </div>
                </div>
            @endif

            @if ($preview['flatbed']['capacity'] > 0)
                <div>
                    <div class="cv-load-preview__capacity-heading">
                        <span class="cv-load-preview__metric-label">Flatbed spots</span>
                        <span class="cv-load-preview__metric-value">
                            {{ $preview['flatbed']['used'] }} of {{ $preview['flatbed']['capacity'] }} used
                        </span>
                    </div>
                    <div class="cv-load-preview__capacity-bar" aria-label="Flatbed capacity">
                        <span
                            class="cv-load-preview__capacity-existing"
                            style="width: {{ $flatbedExistingPercent }}%"
                        ></span>
                        <span
                            class="cv-load-preview__capacity-draft"
                            style="width: {{ $flatbedDraftPercent }}%"
                        ></span>
                    </div>
                </div>
            @endif
        </div>

        <div class="cv-load-preview__legend">
            <span class="cv-load-preview__legend-item">
                <span class="cv-load-preview__swatch cv-load-preview__swatch--existing"></span>
                Existing trip
            </span>
            <span class="cv-load-preview__legend-item">
                <span class="cv-load-preview__swatch cv-load-preview__swatch--draft"></span>
                This order
            </span>
            <span class="cv-load-preview__legend-item">
                <span class="cv-load-preview__swatch cv-load-preview__swatch--remaining"></span>
                Remaining
            </span>
        </div>

        @if (($diagram['available'] ?? false) && $racks->isNotEmpty())
            <div class="cv-load-preview__diagram">
                <div class="cv-load-preview__diagram-title">Rack placement</div>
                <div
                    class="cv-load-preview__rack-strip"
                    style="--rack-count: {{ max(1, $racks->count()) }}; --rack-levels: {{ $maximumRackLevels }}"
                >
                    @foreach ($racks as $rack)
                        <div class="cv-load-preview__rack">
                            <div class="cv-load-preview__rack-cells">
                                @if (empty($rack['type_code']))
                                    <div class="cv-load-preview__rack-cell cv-load-preview__rack-cell--open cv-load-preview__rack-cell--open-rack">
                                        Open
                                    </div>
                                @else
                                    @for ($level = $maximumRackLevels; $level >= 1; $level--)
                                        @php
                                            $cell = $rack['cells'][$level - 1] ?? null;
                                            $isRealLevel = $level <= (int) ($rack['level_count'] ?? 0);
                                            $isDraftCell = $cell
                                                && (int) ($cell['stop_sequence'] ?? 0) === $draftStopSequence;
                                            $cellClass = ! $isRealLevel
                                                ? 'spacer'
                                                : ($cell
                                                    ? ($isDraftCell ? 'draft' : 'existing')
                                                    : 'open');
                                        @endphp
                                        <div class="cv-load-preview__rack-cell cv-load-preview__rack-cell--{{ $cellClass }}">
                                            @if ($cell)
                                                {{ $cell['code'] }}
                                            @elseif ($isRealLevel)
                                                <span aria-label="Open rack position">—</span>
                                            @endif
                                        </div>
                                    @endfor
                                @endif
                            </div>
                            <div class="cv-load-preview__rack-label">R{{ $rack['number'] }}</div>
                        </div>
                    @endforeach
                </div>

                @if ($preview['flatbed']['capacity'] > 0)
                    <div class="cv-load-preview__flatbed">
                        <div class="cv-load-preview__diagram-title">Flatbed fallback</div>
                        <div
                            class="cv-load-preview__flatbed-strip"
                            style="--flatbed-count: {{ $preview['flatbed']['capacity'] }}"
                        >
                            @for ($spotNumber = 1; $spotNumber <= $preview['flatbed']['capacity']; $spotNumber++)
                                @php
                                    $spot = $flatbedSpots->get($spotNumber);
                                    $isDraftSpot = $spot
                                        && (int) ($spot['stop_sequence'] ?? 0) === $draftStopSequence;
                                    $spotClass = $spot
                                        ? ($isDraftSpot ? 'draft' : 'existing')
                                        : 'open';
                                @endphp
                                <div>
                                    <div class="cv-load-preview__flatbed-cell cv-load-preview__flatbed-cell--{{ $spotClass }}">
                                        {{ $spot['code'] ?? 'Open' }}
                                    </div>
                                    <div class="cv-load-preview__flatbed-label">P{{ $spotNumber }}</div>
                                </div>
                            @endfor
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <div class="cv-load-preview__impact">
            <div class="cv-load-preview__impact-row">
                <span class="cv-load-preview__impact-label">This draft adds</span>
                <span class="cv-load-preview__impact-value">
                    {{ $preview['draft']['product_units'] }}
                    {{ \Illuminate\Support\Str::plural('unit', $preview['draft']['product_units']) }}
                    · {{ number_format($preview['draft']['known_weight_lbs']) }} lb
                    @if ($preview['racks']['draft_touched'] > 0)
                        · {{ $preview['racks']['draft_touched'] }}
                        {{ \Illuminate\Support\Str::plural('rack', $preview['racks']['draft_touched']) }}
                    @endif
                </span>
            </div>
            <div class="cv-load-preview__impact-row">
                <span class="cv-load-preview__impact-label">Remaining after save</span>
                <span class="cv-load-preview__impact-value">
                    @if ($preview['racks']['capacity'] > 0)
                        {{ $preview['racks']['remaining'] }}
                        {{ \Illuminate\Support\Str::plural('rack', $preview['racks']['remaining']) }}
                        ·
                    @endif
                    @if ($preview['flatbed']['capacity'] > 0)
                        {{ $preview['flatbed']['remaining'] }}
                        flatbed
                        {{ \Illuminate\Support\Str::plural('spot', $preview['flatbed']['remaining']) }}
                        ·
                    @endif
                    {{ $preview['weight']['remaining'] === null ? 'Unknown' : number_format($preview['weight']['remaining']) . ' lb' }}
                </span>
            </div>
        </div>

        <div class="cv-load-preview__result cv-load-preview__result--{{ $preview['status'] }}">
            {{ $preview['message'] }}
        </div>

        @if ($preview['warnings'] !== [])
            <ul class="cv-load-preview__warnings">
                @foreach (array_slice($preview['warnings'], 0, 4) as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        @endif

        <p class="cv-load-preview__note">
            Preview only — final placement recalculates when the order or trip is saved.
        </p>
    </div>
</div>
