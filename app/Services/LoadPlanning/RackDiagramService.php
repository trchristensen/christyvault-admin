<?php

namespace App\Services\LoadPlanning;

use App\Models\LoadingProfile;

class RackDiagramService
{
    public function forDemand(LoadDemandResult $demand): array
    {
        $preferred = $this->buildDiagram($demand, compactSplitDoubles: false);

        if (! $preferred['available'] || ! $this->hasSplitDoubleItems($demand)) {
            return $preferred;
        }

        $compact = $this->buildDiagram($demand, compactSplitDoubles: true);

        return $compact['placed_units'] > $preferred['placed_units']
            ? $compact
            : $preferred;
    }

    private function buildDiagram(LoadDemandResult $demand, bool $compactSplitDoubles): array
    {
        $vehicle = $demand->vehicleConfiguration;
        $rackSpotCount = (int) ($vehicle['rack_spot_count'] ?? 0);

        if (($vehicle['type'] ?? null) !== 'rack_trailer' || $rackSpotCount < 1) {
            return [
                'available' => false,
                'message' => $vehicle
                    ? 'A rack diagram is not available for this vehicle configuration.'
                    : 'Select a rack-trailer vehicle configuration to generate the diagram.',
                'racks' => [],
                'legend' => [],
                'unplaced' => [],
                'placed_units' => 0,
                'used_rack_spots' => 0,
            ];
        }

        $racks = collect(range(1, $rackSpotCount))
            ->map(fn (int $number): array => [
                'number' => $number,
                'type_code' => null,
                'type_label' => 'Unassigned',
                'level_count' => 0,
                'cells' => [],
                'stop_sequences' => [],
            ])
            ->all();
        $legend = [];
        $unplaced = [];
        $usedCodes = [];
        $placedUnits = 0;

        foreach (array_reverse($demand->stops) as $stop) {
            $items = collect($stop['items'])
                ->map(fn (array $item, int $index): array => [...$item, '_original_index' => $index])
                ->sort(function (array $left, array $right): int {
                    $weightOrder = ($right['unit_weight_lbs'] ?? -1) <=> ($left['unit_weight_lbs'] ?? -1);

                    return $weightOrder !== 0
                        ? $weightOrder
                        : $left['_original_index'] <=> $right['_original_index'];
                });

            foreach ($items as $item) {
                if (! $item['quantity'] || $item['fill_load']) {
                    continue;
                }

                $code = $this->uniqueShortCode($item['sku'], $usedCodes);
                $legend[$item['sku']] = [
                    'code' => $code,
                    'sku' => $item['sku'],
                    'name' => $item['name'],
                    'placement_strategy' => $item['placement_strategy']
                        ?? LoadingProfile::PLACEMENT_ONE_PER_LEVEL,
                    'units_per_rack_position' => $item['units_per_rack_position'] ?? 1,
                ];

                if ($item['handling_method'] === LoadingProfile::HANDLING_PALLET) {
                    $unplaced[] = $this->unplaced($item, $stop, 'Pallet rack placement is not implemented yet.');

                    continue;
                }

                if ($item['rack_requirement'] === LoadingProfile::RACK_SINGLE) {
                    $placed = $this->placeOversized($racks, $item, $stop, $code);
                } elseif ($item['rack_requirement'] === LoadingProfile::RACK_STANDARD) {
                    if (! $item['required_rack_type'] || ! $item['required_rack_level_count']) {
                        $unplaced[] = $this->unplaced(
                            $item,
                            $stop,
                            'Rack height/type has not been confirmed for this product.',
                        );

                        continue;
                    }

                    $placed = ($item['placement_strategy'] ?? null) === LoadingProfile::PLACEMENT_FULL_TOP_SPLIT_BOTTOM_PAIR
                        ? ($compactSplitDoubles
                            ? $this->placeSplitDoubleCompact($racks, $item, $stop, $code)
                            : $this->placeSplitDoubleWholeFirst($racks, $item, $stop, $code))
                        : $this->placeStandard($racks, $item, $stop, $code);
                } else {
                    $unplaced[] = $this->unplaced($item, $stop, 'This product does not have a rack placement rule.');

                    continue;
                }

                $placedUnits += $placed;
                $remaining = (int) $item['quantity'] - $placed;

                if ($remaining > 0) {
                    $reason = match (true) {
                        ($item['placement_strategy'] ?? null) === LoadingProfile::PLACEMENT_FULL_TOP_SPLIT_BOTTOM_PAIR => 'Not enough paired 2-high rack positions.',
                        $item['required_rack_level'] === LoadingProfile::LEVEL_BOTTOM => 'Not enough eligible bottom rack positions.',
                        default => 'Not enough compatible rack positions.',
                    };
                    $unplaced[] = $this->unplaced($item, $stop, $reason, $remaining);
                }
            }
        }

        $this->sortRacksByWeightWithinStops($racks);

        foreach ($racks as &$rack) {
            $rack['product_weight_lbs'] = round($this->rackWeight($rack), 2);
            $rack['has_unknown_weight'] = collect($rack['cells'])
                ->filter()
                ->contains(fn (array $cell): bool => $cell['unit_weight_lbs'] === null);
        }
        unset($rack);

        return [
            'available' => true,
            'message' => null,
            'racks' => $racks,
            'legend' => array_values($legend),
            'unplaced' => $unplaced,
            'placed_units' => $placedUnits,
            'used_rack_spots' => collect($racks)->whereNotNull('type_code')->count(),
            'rack_spot_count' => $rackSpotCount,
        ];
    }

    private function placeOversized(array &$racks, array $item, array $stop, string $code): int
    {
        $placed = 0;

        for ($unit = 0; $unit < (int) $item['quantity']; $unit++) {
            $rackIndex = $this->findEmptyRack($racks, (int) $stop['sequence']);

            if ($rackIndex === null) {
                break;
            }

            $racks[$rackIndex] = [
                ...$racks[$rackIndex],
                'type_code' => 'oversized_single',
                'type_label' => 'Single',
                'level_count' => 1,
                'cells' => [[...$this->cell($item, $stop, $code), 'level' => 1]],
                'stop_sequences' => [(int) $stop['sequence']],
            ];
            $placed++;
        }

        return $placed;
    }

    private function placeStandard(array &$racks, array $item, array $stop, string $code): int
    {
        $rackType = $item['required_rack_type'];
        $levelCount = (int) ($item['required_rack_level_count'] ?? 0);

        if (! $rackType || $levelCount < 1) {
            return 0;
        }

        $allowedRackTypes = $item['allowed_rack_type_codes'] ?? [];

        if ($allowedRackTypes === []) {
            $allowedRackTypes = [$rackType];
        }

        $unitsPerPosition = max(1, (int) ($item['units_per_rack_position'] ?? 1));
        $placed = 0;

        // Reuse compatible openings first. This reduces rearward cargo and avoids
        // consuming another rack spot when a rack for the same stop has space.
        for ($rackIndex = 0; $rackIndex < count($racks); $rackIndex++) {
            if ($placed >= (int) $item['quantity']) {
                break;
            }

            if ($racks[$rackIndex]['type_code'] === null
                || ! $this->rackCanAcceptStop($racks[$rackIndex], (int) $stop['sequence'])
                || ! in_array($racks[$rackIndex]['type_code'], $allowedRackTypes, true)) {
                continue;
            }

            $placed = $this->fillStandardRack(
                $racks[$rackIndex],
                $item,
                $stop,
                $code,
                $unitsPerPosition,
                $placed,
            );
        }

        // Open the preferred rack type only after every compatible gap has been used.
        for ($rackIndex = 0; $rackIndex < count($racks); $rackIndex++) {
            if ($placed >= (int) $item['quantity']) {
                break;
            }

            if ($racks[$rackIndex]['type_code'] !== null
                || ! $this->rackCanAcceptStop($racks[$rackIndex], (int) $stop['sequence'])) {
                continue;
            }

            $racks[$rackIndex]['type_code'] = $rackType;
            $racks[$rackIndex]['type_label'] = $levelCount.'-high';
            $racks[$rackIndex]['level_count'] = $levelCount;
            $racks[$rackIndex]['cells'] = array_fill(0, $levelCount, null);
            $placed = $this->fillStandardRack(
                $racks[$rackIndex],
                $item,
                $stop,
                $code,
                $unitsPerPosition,
                $placed,
            );
        }

        return $placed;
    }

    private function fillStandardRack(
        array &$rack,
        array $item,
        array $stop,
        string $code,
        int $unitsPerPosition,
        int $placed,
    ): int {
        $allowedLevels = $item['required_rack_level'] === LoadingProfile::LEVEL_BOTTOM
            ? [0]
            : range(0, (int) $rack['level_count'] - 1);

        foreach ($allowedLevels as $levelIndex) {
            if ($placed >= (int) $item['quantity']) {
                break;
            }

            if ($rack['cells'][$levelIndex] !== null) {
                continue;
            }

            $positionQuantity = min(
                $unitsPerPosition,
                (int) $item['quantity'] - $placed,
            );
            $displayCode = $positionQuantity > 1
                ? $positionQuantity.'×'.$code
                : $code;
            $rack['cells'][$levelIndex] = [
                ...$this->cell($item, $stop, $displayCode),
                'level' => $levelIndex + 1,
                'quantity' => $positionQuantity,
                'unit_fraction' => $positionQuantity,
                'position_capacity' => $unitsPerPosition,
            ];
            $rack['stop_sequences'] = array_values(array_unique([
                ...$rack['stop_sequences'],
                (int) $stop['sequence'],
            ]));
            $placed += $positionQuantity;
        }

        return $placed;
    }

    private function placeSplitDoubleWholeFirst(array &$racks, array $item, array $stop, string $code): int
    {
        $quantity = (int) $item['quantity'];
        $rackType = $item['required_rack_type'];
        $levelCount = (int) ($item['required_rack_level_count'] ?? 0);

        if ($levelCount !== 2) {
            return 0;
        }

        $allowedRackTypes = $item['allowed_rack_type_codes'] ?? [];

        if ($allowedRackTypes === []) {
            $allowedRackTypes = [$rackType];
        }

        $placed = 0;

        // A whole G4/G5 belongs on top. Reuse an existing compatible rack first,
        // then open one rack per whole product. Splitting is deferred until every
        // available top position has been exhausted.
        for ($rackIndex = 0; $rackIndex < count($racks) && $placed < $quantity; $rackIndex++) {
            if (! in_array($racks[$rackIndex]['type_code'], $allowedRackTypes, true)
                || ! $this->rackCanAcceptStop($racks[$rackIndex], (int) $stop['sequence'])
                || ($racks[$rackIndex]['cells'][1] ?? null) !== null) {
                continue;
            }

            $racks[$rackIndex]['cells'][1] = [
                ...$this->cell($item, $stop, $code),
                'level' => 2,
                'component' => 'whole',
                'unit_fraction' => 1,
            ];
            $racks[$rackIndex]['stop_sequences'] = array_values(array_unique([
                ...$racks[$rackIndex]['stop_sequences'],
                (int) $stop['sequence'],
            ]));
            $placed++;
        }

        for ($rackIndex = 0; $rackIndex < count($racks) && $placed < $quantity; $rackIndex++) {
            if ($racks[$rackIndex]['type_code'] !== null
                || ! $this->rackCanAcceptStop($racks[$rackIndex], (int) $stop['sequence'])) {
                continue;
            }

            $racks[$rackIndex] = [
                ...$racks[$rackIndex],
                'type_code' => $rackType,
                'type_label' => '2-high · whole-double',
                'level_count' => 2,
                'cells' => [null, [
                    ...$this->cell($item, $stop, $code),
                    'level' => 2,
                    'component' => 'whole',
                    'unit_fraction' => 1,
                ]],
                'stop_sequences' => [(int) $stop['sequence']],
            ];
            $placed++;
        }

        $bottomRackIndexes = collect($racks)
            ->keys()
            ->filter(fn (int $rackIndex): bool => in_array($racks[$rackIndex]['type_code'], $allowedRackTypes, true)
                && $this->rackCanAcceptStop($racks[$rackIndex], (int) $stop['sequence'])
                && ($racks[$rackIndex]['cells'][0] ?? null) === null
                && data_get($racks[$rackIndex], 'cells.1.sku') === $item['sku']
                && data_get($racks[$rackIndex], 'cells.1.component') === 'whole')
            ->values();
        $splitPair = 0;

        while ($placed < $quantity && $bottomRackIndexes->count() >= 2) {
            $splitPair++;
            $pair = $bottomRackIndexes->splice(0, 2)->values();

            foreach ($pair as $rackIndex) {
                $racks[$rackIndex]['cells'][0] = [
                    ...$this->cell($item, $stop, '½'.$code),
                    'level' => 1,
                    'component' => 'half',
                    'split_pair' => $splitPair,
                    'unit_fraction' => 0.5,
                ];
            }

            $placed++;
        }

        return $placed;
    }

    private function placeSplitDoubleCompact(array &$racks, array $item, array $stop, string $code): int
    {
        $quantity = (int) $item['quantity'];
        $rackType = $item['required_rack_type'];
        $levelCount = (int) ($item['required_rack_level_count'] ?? 0);

        if ($levelCount !== 2) {
            return 0;
        }

        $emptyRackIndexes = [];

        for ($rackIndex = 0; $rackIndex < count($racks); $rackIndex++) {
            if ($racks[$rackIndex]['type_code'] === null) {
                $emptyRackIndexes[] = $rackIndex;
            }
        }

        $rackCount = min(count($emptyRackIndexes), (int) ceil(($quantity * 2) / 3));
        $selectedRackIndexes = array_slice($emptyRackIndexes, 0, $rackCount);
        $placed = min($quantity, $rackCount + intdiv($rackCount, 2));
        $wholeUnits = min($placed, $rackCount);
        $splitUnits = $placed - $wholeUnits;

        foreach ($selectedRackIndexes as $rackIndex) {
            $racks[$rackIndex] = [
                ...$racks[$rackIndex],
                'type_code' => $rackType,
                'type_label' => '2-high · split-double',
                'level_count' => 2,
                'cells' => [null, null],
                'stop_sequences' => [(int) $stop['sequence']],
            ];
        }

        for ($unit = 0; $unit < $wholeUnits; $unit++) {
            $rackIndex = $selectedRackIndexes[$unit];
            $racks[$rackIndex]['cells'][1] = [
                ...$this->cell($item, $stop, $code),
                'level' => 2,
                'component' => 'whole',
                'unit_fraction' => 1,
            ];
        }

        for ($splitUnit = 0; $splitUnit < $splitUnits; $splitUnit++) {
            foreach ([0, 1] as $half) {
                $rackIndex = $selectedRackIndexes[($splitUnit * 2) + $half];
                $racks[$rackIndex]['cells'][0] = [
                    ...$this->cell($item, $stop, '½'.$code),
                    'level' => 1,
                    'component' => 'half',
                    'split_pair' => $splitUnit + 1,
                    'unit_fraction' => 0.5,
                ];
            }
        }

        return $placed;
    }

    private function hasSplitDoubleItems(LoadDemandResult $demand): bool
    {
        return collect($demand->stops)
            ->flatMap(fn (array $stop): array => $stop['items'])
            ->contains(fn (array $item): bool => ($item['placement_strategy'] ?? null)
                === LoadingProfile::PLACEMENT_FULL_TOP_SPLIT_BOTTOM_PAIR);
    }

    private function findEmptyRack(array $racks, int $stopSequence): ?int
    {
        for ($rackIndex = 0; $rackIndex < count($racks); $rackIndex++) {
            if ($racks[$rackIndex]['type_code'] === null && $this->rackCanAcceptStop($racks[$rackIndex], $stopSequence)) {
                return $rackIndex;
            }
        }

        return null;
    }

    private function rackCanAcceptStop(array $rack, int $stopSequence): bool
    {
        return $rack['stop_sequences'] === [] || in_array($stopSequence, $rack['stop_sequences'], true);
    }

    private function cell(array $item, array $stop, string $code): array
    {
        return [
            'code' => $code,
            'sku' => $item['sku'],
            'name' => $item['name'],
            'stop_sequence' => (int) $stop['sequence'],
            'unit_weight_lbs' => $item['unit_weight_lbs'],
        ];
    }

    private function sortRacksByWeightWithinStops(array &$racks): void
    {
        $stopSequences = collect($racks)
            ->flatMap(fn (array $rack): array => $rack['stop_sequences'])
            ->unique()
            ->values();

        foreach ($stopSequences as $stopSequence) {
            $indexes = collect($racks)
                ->keys()
                ->filter(fn (int $index): bool => in_array($stopSequence, $racks[$index]['stop_sequences'], true))
                ->values();
            $sorted = $indexes
                ->map(fn (int $index): array => [...$racks[$index], '_original_index' => $index])
                ->sort(function (array $left, array $right): int {
                    $weightOrder = $this->rackWeight($right) <=> $this->rackWeight($left);

                    return $weightOrder !== 0
                        ? $weightOrder
                        : $left['_original_index'] <=> $right['_original_index'];
                })
                ->values();

            foreach ($indexes as $offset => $targetIndex) {
                $rack = $sorted[$offset];
                unset($rack['_original_index']);
                $rack['number'] = $racks[$targetIndex]['number'];
                $racks[$targetIndex] = $rack;
            }
        }
    }

    private function rackWeight(array $rack): float
    {
        return collect($rack['cells'])->sum(function (?array $cell): float {
            if (! $cell || $cell['unit_weight_lbs'] === null) {
                return 0.0;
            }

            return (float) $cell['unit_weight_lbs'] * (float) ($cell['unit_fraction'] ?? 1);
        });
    }

    private function unplaced(
        array $item,
        array $stop,
        string $reason,
        ?int $quantity = null,
    ): array {
        return [
            'sku' => $item['sku'],
            'name' => $item['name'],
            'quantity' => $quantity ?? (int) $item['quantity'],
            'stop_sequence' => (int) $stop['sequence'],
            'reason' => $reason,
        ];
    }

    private function uniqueShortCode(string $sku, array &$usedCodes): string
    {
        if (isset($usedCodes[$sku])) {
            return $usedCodes[$sku];
        }

        $normalized = mb_strtoupper(trim($sku));
        $code = match (true) {
            preg_match('/^V3086-(.+)$/', $normalized, $matches) === 1 => 'V'.$matches[1],
            preg_match('/^L3086-(.+)$/', $normalized, $matches) === 1 => 'L'.$matches[1],
            preg_match('/^G3086-(.+)$/', $normalized, $matches) === 1 => 'G'.$matches[1],
            preg_match('/^W3086-(.+)$/', $normalized, $matches) === 1 => 'W'.$matches[1],
            preg_match('/^2-3690G([45])$/', $normalized, $matches) === 1 => 'G'.$matches[1].'C',
            default => mb_substr(str_replace(['-', ' '], '', $normalized), 0, 7),
        };
        $existingSku = array_search($code, $usedCodes, true);

        if ($existingSku !== false && $existingSku !== $sku) {
            $code = mb_substr(str_replace(['-', ' '], '', $normalized), 0, 10);
        }

        $usedCodes[$sku] = $code;

        return $code;
    }
}
