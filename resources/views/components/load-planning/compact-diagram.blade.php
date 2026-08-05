@props([
    'diagram',
    'draftStopSequence' => null,
])

@php
    $racks = collect($diagram['racks'] ?? []);
    $flatbedSpots = collect($diagram['flatbed_pallets'] ?? [])->keyBy('spot_number');
    $maximumRackLevels = max(1, (int) $racks->max('level_count'));
    $flatbedCapacity = (int) ($diagram['flatbed_pallet_capacity'] ?? 0);
@endphp

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
                                    $isDraftCell = $draftStopSequence !== null
                                        && $cell
                                        && (int) ($cell['stop_sequence'] ?? 0) === (int) $draftStopSequence;
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

        @if ($flatbedCapacity > 0)
            <div class="cv-load-preview__flatbed">
                <div class="cv-load-preview__diagram-title">Flatbed fallback</div>
                <div
                    class="cv-load-preview__flatbed-strip"
                    style="--flatbed-count: {{ $flatbedCapacity }}"
                >
                    @for ($spotNumber = 1; $spotNumber <= $flatbedCapacity; $spotNumber++)
                        @php
                            $spot = $flatbedSpots->get($spotNumber);
                            $isDraftSpot = $draftStopSequence !== null
                                && $spot
                                && (int) ($spot['stop_sequence'] ?? 0) === (int) $draftStopSequence;
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
