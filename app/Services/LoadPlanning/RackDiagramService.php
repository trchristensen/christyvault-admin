<?php

namespace App\Services\LoadPlanning;

use App\Models\LoadingProfile;

class RackDiagramService
{
    private const PAIRING_STANDARD_FILLER = 'standard_filler';

    private const PAIRING_WILBERT_VAULT = 'wilbert_vault';

    private const PAIRING_GARDEN_DOUBLE = 'garden_double';

    private const PAIRING_LINER = 'liner';

    private const PAIRING_AVOID = 100;

    public function forDemand(LoadDemandResult $demand): array
    {
        $flatbedCapacity = max(0, (int) ($demand->vehicleConfiguration['flatbed_pallet_capacity'] ?? 0));
        $compactOptions = $this->hasSplitDoubleItems($demand) ? [false, true] : [false];
        $best = null;

        for ($flatbedPalletTarget = 0; $flatbedPalletTarget <= $flatbedCapacity; $flatbedPalletTarget++) {
            foreach ($compactOptions as $compactSplitDoubles) {
                $candidate = $this->buildDiagram(
                    $demand,
                    compactSplitDoubles: $compactSplitDoubles,
                    flatbedPalletTarget: $flatbedPalletTarget,
                );

                if ($best === null || $this->diagramIsBetter($candidate, $best)) {
                    $best = $candidate;
                }
            }
        }

        return $best;
    }

    private function diagramIsBetter(array $candidate, array $current): bool
    {
        if (($candidate['placed_units'] ?? 0) !== ($current['placed_units'] ?? 0)) {
            return ($candidate['placed_units'] ?? 0) > ($current['placed_units'] ?? 0);
        }

        return ($candidate['flatbed_pallets_used'] ?? 0) < ($current['flatbed_pallets_used'] ?? 0);
    }

    private function buildDiagram(
        LoadDemandResult $demand,
        bool $compactSplitDoubles,
        int $flatbedPalletTarget,
    ): array {
        $vehicle = $demand->vehicleConfiguration;
        $rackSpotCount = (int) ($vehicle['rack_spot_count'] ?? 0);
        $flatbedPalletCapacity = max(0, (int) ($vehicle['flatbed_pallet_capacity'] ?? 0));

        if (($vehicle['type'] ?? null) !== 'rack_trailer' || $rackSpotCount < 1) {
            return [
                'available' => false,
                'message' => $vehicle
                    ? 'A rack diagram is not available for this vehicle configuration.'
                    : 'Select a rack-trailer vehicle configuration to generate the diagram.',
                'racks' => [],
                'legend' => [],
                'unplaced' => [],
                'non_rack_cargo' => [],
                'placed_units' => 0,
                'used_rack_spots' => 0,
                'flatbed_pallets' => [],
                'flatbed_pallets_used' => 0,
                'flatbed_pallet_capacity' => $flatbedPalletCapacity,
            ];
        }

        $racks = collect(range(1, $rackSpotCount))
            ->map(fn (int $number): array => [
                'number' => $number,
                'type_code' => null,
                'type_label' => 'Unassigned',
                'level_count' => 0,
                'pallet_capable_levels' => 0,
                'pallets_per_capable_level' => 0,
                'cells' => [],
                'stop_sequences' => [],
            ])
            ->all();
        $legend = [];
        $unplaced = [];
        $usedCodes = [];
        $placedUnits = 0;
        $flatbedPallets = [];
        $nonRackCargo = [];

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
                if (! $item['quantity'] || ($item['fill_load'] && ! ($item['fill_resolved'] ?? false))) {
                    continue;
                }

                $code = $this->uniqueShortCode($item['sku'], $usedCodes);
                $legend[$item['sku']] = [
                    'code' => $code,
                    'sku' => $item['sku'],
                    'name' => $item['name'],
                    'unit_weight_lbs' => $item['unit_weight_lbs'],
                    'handling_method' => $item['handling_method'],
                    'rack_requirement' => $item['rack_requirement'],
                    'placement_strategy' => $item['placement_strategy']
                        ?? LoadingProfile::PLACEMENT_ONE_PER_LEVEL,
                    'units_per_rack_position' => $item['units_per_rack_position'] ?? 1,
                ];

                if ($item['handling_method'] === LoadingProfile::HANDLING_LOOSE
                    || $item['rack_requirement'] === LoadingProfile::RACK_NONE) {
                    $placed = (int) $item['quantity'];
                    $nonRackCargo[] = [
                        'code' => $code,
                        'sku' => $item['sku'],
                        'name' => $item['name'],
                        'quantity' => $placed,
                        'stop_sequence' => (int) $stop['sequence'],
                        'order_number' => $stop['order_number'] ?? null,
                        'location_name' => $stop['location_name'] ?? null,
                        'total_weight_lbs' => $item['total_weight_lbs'],
                    ];
                } elseif ($item['handling_method'] === LoadingProfile::HANDLING_PALLET) {
                    $placed = $this->placePallets(
                        $racks,
                        $flatbedPallets,
                        $flatbedPalletTarget,
                        $item,
                        $stop,
                        $code,
                    );
                } elseif ($item['rack_requirement'] === LoadingProfile::RACK_SINGLE) {
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
                        $item['handling_method'] === LoadingProfile::HANDLING_PALLET => 'Not enough compatible pallet rack or fallback flatbed positions.',
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
            'non_rack_cargo' => $nonRackCargo,
            'placed_units' => $placedUnits,
            'used_rack_spots' => collect($racks)->whereNotNull('type_code')->count(),
            'rack_spot_count' => $rackSpotCount,
            'flatbed_pallets' => $flatbedPallets,
            'flatbed_pallets_used' => count($flatbedPallets),
            'flatbed_pallet_capacity' => $flatbedPalletCapacity,
        ];
    }

    private function placePallets(
        array &$racks,
        array &$flatbedPallets,
        int $flatbedPalletTarget,
        array $item,
        array $stop,
        string $code,
    ): int {
        $unitsPerPallet = (int) ($item['units_per_pallet'] ?? 0);
        $rackType = $item['required_rack_type'] ?? null;
        $levelCount = (int) ($item['required_rack_level_count'] ?? 0);

        if ($unitsPerPallet < 1 || ! $rackType || $levelCount < 1) {
            return 0;
        }

        $allowedRackTypes = $item['allowed_rack_type_codes'] ?? [];

        if ($allowedRackTypes === []) {
            $allowedRackTypes = [$rackType];
        }

        $remainingUnits = (int) $item['quantity'];
        $placedUnits = 0;

        while ($remainingUnits > 0) {
            $mixedUnits = $this->addToCompatiblePartialPallet(
                $racks,
                $item,
                $stop,
                $code,
                $remainingUnits,
                $unitsPerPallet,
                $allowedRackTypes,
            );

            if ($mixedUnits > 0) {
                $placedUnits += $mixedUnits;
                $remainingUnits -= $mixedUnits;

                continue;
            }

            $mixedFlatbedUnits = $this->addToCompatiblePartialFlatbedPallet(
                $flatbedPallets,
                $item,
                $stop,
                $code,
                $remainingUnits,
                $unitsPerPallet,
            );

            if ($mixedFlatbedUnits > 0) {
                $placedUnits += $mixedFlatbedUnits;
                $remainingUnits -= $mixedFlatbedUnits;

                continue;
            }

            $palletUnits = min($unitsPerPallet, $remainingUnits);

            if (count($flatbedPallets) < $flatbedPalletTarget) {
                $flatbedPallets[] = [
                    ...$this->pallet($item, $code, $palletUnits, $unitsPerPallet),
                    'spot_number' => count($flatbedPallets) + 1,
                    'stop_sequence' => (int) $stop['sequence'],
                    'order_number' => $stop['order_number'] ?? null,
                    'location_name' => $stop['location_name'] ?? null,
                ];
                $placedUnits += $palletUnits;
                $remainingUnits -= $palletUnits;

                continue;
            }

            $rackIndex = collect($racks)
                ->keys()
                ->first(fn (int $rackIndex): bool => in_array($racks[$rackIndex]['type_code'], $allowedRackTypes, true)
                    && $this->rackCanAcceptStop($racks[$rackIndex], (int) $stop['sequence'])
                    && $this->rackHasPalletPosition($racks[$rackIndex]));

            if ($rackIndex === null) {
                $rackIndex = $this->findEmptyRack($racks, (int) $stop['sequence']);

                if ($rackIndex === null) {
                    break;
                }

                $racks[$rackIndex]['type_code'] = $rackType;
                $racks[$rackIndex]['type_label'] = $levelCount.'-high · pallets';
                $racks[$rackIndex]['level_count'] = $levelCount;
                $this->configurePalletCapacity($racks[$rackIndex], $item, $rackType);
                $racks[$rackIndex]['cells'] = array_fill(0, $levelCount, null);
            }

            if (! $this->addPalletToRack(
                $racks[$rackIndex],
                $item,
                $stop,
                $code,
                $palletUnits,
                $unitsPerPallet,
            )) {
                break;
            }

            $placedUnits += $palletUnits;
            $remainingUnits -= $palletUnits;
        }

        return $placedUnits;
    }

    private function rackHasPalletPosition(array $rack): bool
    {
        $palletLevels = min(
            (int) ($rack['pallet_capable_levels'] ?? 0),
            (int) ($rack['level_count'] ?? 0),
        );
        $palletsPerLevel = (int) ($rack['pallets_per_capable_level'] ?? 0);

        if ($palletLevels < 1 || $palletsPerLevel < 1) {
            return false;
        }

        for ($levelIndex = 0; $levelIndex < $palletLevels; $levelIndex++) {
            $cell = $rack['cells'][$levelIndex] ?? null;

            if ($cell === null
                || (($cell['is_pallet_level'] ?? false)
                    && count($cell['pallets'] ?? []) < $palletsPerLevel)) {
                return true;
            }
        }

        return false;
    }

    private function addPalletToRack(
        array &$rack,
        array $item,
        array $stop,
        string $code,
        int $palletUnits,
        int $unitsPerPallet,
    ): bool {
        $palletLevels = min(
            (int) ($rack['pallet_capable_levels'] ?? 0),
            (int) ($rack['level_count'] ?? 0),
        );
        $palletsPerLevel = (int) ($rack['pallets_per_capable_level'] ?? 0);

        for ($levelIndex = 0; $levelIndex < $palletLevels; $levelIndex++) {
            $cell = $rack['cells'][$levelIndex] ?? null;

            if ($cell !== null && (! ($cell['is_pallet_level'] ?? false)
                || count($cell['pallets'] ?? []) >= $palletsPerLevel)) {
                continue;
            }

            $pallet = $this->pallet($item, $code, $palletUnits, $unitsPerPallet);

            if ($cell === null) {
                $cell = [
                    ...$this->cell($item, $stop, $pallet['code']),
                    'level' => $levelIndex + 1,
                    'is_pallet_level' => true,
                    'pallets' => [],
                ];
            }

            $cell['pallets'][] = $pallet;
            $this->refreshPalletCell($cell);
            $rack['cells'][$levelIndex] = $cell;
            $rack['stop_sequences'] = array_values(array_unique([
                ...$rack['stop_sequences'],
                (int) $stop['sequence'],
            ]));

            return true;
        }

        return false;
    }

    private function pallet(array $item, string $code, int $palletUnits, int $unitsPerPallet): array
    {
        $totalWeight = $item['unit_weight_lbs'] === null
            ? null
            : (float) $item['unit_weight_lbs'] * $palletUnits;

        return [
            'code' => $palletUnits.'×'.$code,
            'sku' => $item['sku'],
            'name' => $item['name'],
            'units' => $palletUnits,
            'capacity' => $unitsPerPallet,
            'capacity_used' => $palletUnits / $unitsPerPallet,
            'compatibility_group' => $item['pallet_compatibility_group'] ?? null,
            'products' => [[
                'code' => $palletUnits.'×'.$code,
                'sku' => $item['sku'],
                'name' => $item['name'],
                'units' => $palletUnits,
                'capacity' => $unitsPerPallet,
                'total_weight_lbs' => $totalWeight,
            ]],
            'total_weight_lbs' => $totalWeight,
        ];
    }

    private function addToCompatiblePartialFlatbedPallet(
        array &$flatbedPallets,
        array $item,
        array $stop,
        string $code,
        int $remainingUnits,
        int $unitsPerPallet,
    ): int {
        $compatibilityGroup = $item['pallet_compatibility_group'] ?? null;

        if (! filled($compatibilityGroup)) {
            return 0;
        }

        foreach ($flatbedPallets as &$pallet) {
            if (($pallet['stop_sequence'] ?? null) !== (int) $stop['sequence']
                || ($pallet['compatibility_group'] ?? null) !== $compatibilityGroup) {
                continue;
            }

            $remainingFraction = max(0, 1 - (float) ($pallet['capacity_used'] ?? 1));
            $unitsThatFit = (int) floor(($remainingFraction * $unitsPerPallet) + 0.000001);

            if ($unitsThatFit < 1) {
                continue;
            }

            $unitsToAdd = min($remainingUnits, $unitsThatFit);
            $this->addProductToPallet($pallet, $item, $code, $unitsToAdd, $unitsPerPallet);
            unset($pallet);

            return $unitsToAdd;
        }
        unset($pallet);

        return 0;
    }

    private function addToCompatiblePartialPallet(
        array &$racks,
        array $item,
        array $stop,
        string $code,
        int $remainingUnits,
        int $unitsPerPallet,
        array $allowedRackTypes,
    ): int {
        $compatibilityGroup = $item['pallet_compatibility_group'] ?? null;

        if (! filled($compatibilityGroup)) {
            return 0;
        }

        foreach ($racks as &$rack) {
            if (! in_array($rack['type_code'], $allowedRackTypes, true)
                || ! $this->rackCanAcceptStop($rack, (int) $stop['sequence'])) {
                continue;
            }

            $palletLevels = min(
                (int) ($rack['pallet_capable_levels'] ?? 0),
                (int) ($rack['level_count'] ?? 0),
            );

            for ($levelIndex = 0; $levelIndex < $palletLevels; $levelIndex++) {
                $cell = &$rack['cells'][$levelIndex];

                if (! ($cell['is_pallet_level'] ?? false)) {
                    unset($cell);

                    continue;
                }

                foreach ($cell['pallets'] as &$pallet) {
                    if (($pallet['compatibility_group'] ?? null) !== $compatibilityGroup) {
                        continue;
                    }

                    $remainingFraction = max(0, 1 - (float) ($pallet['capacity_used'] ?? 1));
                    $unitsThatFit = (int) floor(($remainingFraction * $unitsPerPallet) + 0.000001);

                    if ($unitsThatFit < 1) {
                        continue;
                    }

                    $unitsToAdd = min($remainingUnits, $unitsThatFit);
                    $this->addProductToPallet($pallet, $item, $code, $unitsToAdd, $unitsPerPallet);
                    $this->refreshPalletCell($cell);

                    unset($pallet, $cell, $rack);

                    return $unitsToAdd;
                }
                unset($pallet, $cell);
            }
        }
        unset($rack);

        return 0;
    }

    private function addProductToPallet(
        array &$pallet,
        array $item,
        string $code,
        int $unitsToAdd,
        int $unitsPerPallet,
    ): void {
        $productWeight = $item['unit_weight_lbs'] === null
            ? null
            : (float) $item['unit_weight_lbs'] * $unitsToAdd;
        $existingProductIndex = collect($pallet['products'] ?? [])
            ->search(fn (array $product): bool => $product['sku'] === $item['sku']);

        if ($existingProductIndex === false) {
            $pallet['products'][] = [
                'code' => $unitsToAdd.'×'.$code,
                'sku' => $item['sku'],
                'name' => $item['name'],
                'units' => $unitsToAdd,
                'capacity' => $unitsPerPallet,
                'total_weight_lbs' => $productWeight,
            ];
        } else {
            $pallet['products'][$existingProductIndex]['units'] += $unitsToAdd;
            $pallet['products'][$existingProductIndex]['code'] = $pallet['products'][$existingProductIndex]['units'].'×'.$code;
            $pallet['products'][$existingProductIndex]['total_weight_lbs'] = $item['unit_weight_lbs'] === null
                ? null
                : (float) $item['unit_weight_lbs'] * $pallet['products'][$existingProductIndex]['units'];
        }

        $pallet['capacity_used'] = min(1, (float) $pallet['capacity_used'] + ($unitsToAdd / $unitsPerPallet));
        $pallet['units'] = collect($pallet['products'])->sum('units');
        $pallet['code'] = collect($pallet['products'])->pluck('code')->implode(' + ');
        $pallet['sku'] = count($pallet['products']) === 1 ? $pallet['products'][0]['sku'] : 'MIXED';
        $pallet['name'] = count($pallet['products']) === 1 ? $pallet['products'][0]['name'] : 'Mixed boxed products';
        $pallet['total_weight_lbs'] = collect($pallet['products'])->contains(
            fn (array $product): bool => $product['total_weight_lbs'] === null,
        ) ? null : collect($pallet['products'])->sum('total_weight_lbs');
    }

    private function refreshPalletCell(array &$cell): void
    {
        $cell['code'] = collect($cell['pallets'])->pluck('code')->implode(' · ');
        $cell['sku'] = count($cell['pallets']) === 1 ? $cell['pallets'][0]['sku'] : 'PALLETS';
        $cell['name'] = 'Palletized products';
        $cell['unit_weight_lbs'] = collect($cell['pallets'])->contains(
            fn (array $loadedPallet): bool => $loadedPallet['total_weight_lbs'] === null,
        ) ? null : collect($cell['pallets'])->sum('total_weight_lbs');
        $cell['unit_fraction'] = 1;
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
                'pallet_capable_levels' => 0,
                'pallets_per_capable_level' => 0,
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

        $reusableRackIndexes = collect($racks)
            ->keys()
            ->filter(fn (int $rackIndex): bool => $racks[$rackIndex]['type_code'] !== null
                && $this->rackCanAcceptStop($racks[$rackIndex], (int) $stop['sequence'])
                && in_array($racks[$rackIndex]['type_code'], $allowedRackTypes, true))
            ->sortBy(fn (int $rackIndex): array => [
                $this->rackPairingPriority($item, $racks[$rackIndex]),
                $rackIndex,
            ])
            ->values();

        // Reuse preferred and neutral openings first. Explicitly discouraged
        // combinations remain available only after empty rack spots are tried.
        foreach ($reusableRackIndexes as $rackIndex) {
            if ($placed >= (int) $item['quantity']) {
                break;
            }

            if ($this->rackPairingPriority($item, $racks[$rackIndex]) >= self::PAIRING_AVOID) {
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
            $this->configurePalletCapacity($racks[$rackIndex], $item, $rackType);
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

        foreach ($reusableRackIndexes as $rackIndex) {
            if ($placed >= (int) $item['quantity']) {
                break;
            }

            if ($this->rackPairingPriority($item, $racks[$rackIndex]) < self::PAIRING_AVOID) {
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

        // Stops normally remain on separate racks. When the truck is otherwise
        // full, allow one physically compatible rack at the boundary between
        // adjacent stops to use an open position rather than waste it.
        if ($placed < (int) $item['quantity']) {
            $boundaryRackIndex = $this->boundaryRackIndex(
                $racks,
                $item,
                (int) $stop['sequence'],
                $allowedRackTypes,
            );

            if ($boundaryRackIndex !== null) {
                $placed = $this->fillStandardRack(
                    $racks[$boundaryRackIndex],
                    $item,
                    $stop,
                    $code,
                    $unitsPerPosition,
                    $placed,
                );
            }
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

        $reusableTopIndexes = collect($racks)
            ->keys()
            ->filter(fn (int $rackIndex): bool => in_array($racks[$rackIndex]['type_code'], $allowedRackTypes, true)
                && $this->rackCanAcceptStop($racks[$rackIndex], (int) $stop['sequence'])
                && ($racks[$rackIndex]['cells'][1] ?? null) === null)
            ->sortBy(fn (int $rackIndex): array => [
                $this->rackPairingPriority($item, $racks[$rackIndex]),
                $rackIndex,
            ])
            ->values();

        // A whole G4/G5 belongs on top. Prefer ordinary singles and liners
        // underneath it, while preserving Wilbert-vault racks when possible.
        foreach ($reusableTopIndexes as $rackIndex) {
            if ($placed >= $quantity) {
                break;
            }

            if ($this->rackPairingPriority($item, $racks[$rackIndex]) >= self::PAIRING_AVOID) {
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
                'pallet_capable_levels' => 1,
                'pallets_per_capable_level' => 2,
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

        foreach ($reusableTopIndexes as $rackIndex) {
            if ($placed >= $quantity) {
                break;
            }

            if ($this->rackPairingPriority($item, $racks[$rackIndex]) < self::PAIRING_AVOID) {
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
                'pallet_capable_levels' => 1,
                'pallets_per_capable_level' => 2,
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
            'pairing_category' => $this->pairingCategory($item),
            'allowed_rack_type_codes' => ($item['allowed_rack_type_codes'] ?? []) === []
                ? [$item['required_rack_type']]
                : $item['allowed_rack_type_codes'],
        ];
    }

    private function boundaryRackIndex(
        array &$racks,
        array $item,
        int $stopSequence,
        array $allowedRackTypes,
    ): ?int {
        $laterStopSequence = $stopSequence + 1;
        $candidateIndexes = collect($racks)
            ->keys()
            ->filter(fn (int $rackIndex): bool => $racks[$rackIndex]['stop_sequences'] === [$laterStopSequence])
            ->sortDesc()
            ->values();

        foreach ($candidateIndexes as $rackIndex) {
            if (in_array($racks[$rackIndex]['type_code'], $allowedRackTypes, true)
                && $this->rackHasOpenLevelForItem($racks[$rackIndex], $item)) {
                return $rackIndex;
            }

            if ($this->convertBoundaryRackForItem($racks[$rackIndex], $item)
                && $this->rackHasOpenLevelForItem($racks[$rackIndex], $item)) {
                return $rackIndex;
            }
        }

        return null;
    }

    private function convertBoundaryRackForItem(array &$rack, array $item): bool
    {
        $targetRackType = $item['required_rack_type'] ?? null;
        $targetLevelCount = (int) ($item['required_rack_level_count'] ?? 0);

        if (! $targetRackType || $targetLevelCount < 1 || $rack['type_code'] === 'oversized_single') {
            return false;
        }

        $occupiedCells = collect($rack['cells'])->filter();

        if ($occupiedCells->contains(fn (array $cell): bool => (int) $cell['level'] > $targetLevelCount
            || ! in_array($targetRackType, $cell['allowed_rack_type_codes'] ?? [], true))) {
            return false;
        }

        $rack['type_code'] = $targetRackType;
        $rack['type_label'] = $targetLevelCount.'-high · shared boundary';
        $rack['level_count'] = $targetLevelCount;
        $this->configurePalletCapacity($rack, $item, $targetRackType);
        $rack['cells'] = array_pad(
            array_slice($rack['cells'], 0, $targetLevelCount),
            $targetLevelCount,
            null,
        );

        return true;
    }

    private function rackHasOpenLevelForItem(array $rack, array $item): bool
    {
        $allowedLevels = ($item['required_rack_level'] ?? LoadingProfile::LEVEL_ANY) === LoadingProfile::LEVEL_BOTTOM
            ? [0]
            : range(0, (int) $rack['level_count'] - 1);

        return collect($allowedLevels)->contains(
            fn (int $levelIndex): bool => ($rack['cells'][$levelIndex] ?? null) === null,
        );
    }

    private function configurePalletCapacity(array &$rack, array $item, string $rackType): void
    {
        $configuration = collect($item['allowed_rack_types'] ?? [])
            ->firstWhere('code', $rackType);

        if (! $configuration) {
            $configuration = match ($rackType) {
                'standard_2_high' => [
                    'pallet_capable_levels' => 1,
                    'pallets_per_capable_level' => 2,
                ],
                'standard_3_high' => [
                    'pallet_capable_levels' => 2,
                    'pallets_per_capable_level' => 2,
                ],
                default => [
                    'pallet_capable_levels' => 0,
                    'pallets_per_capable_level' => 0,
                ],
            };
        }

        $rack['pallet_capable_levels'] = (int) ($configuration['pallet_capable_levels'] ?? 0);
        $rack['pallets_per_capable_level'] = (int) ($configuration['pallets_per_capable_level'] ?? 0);
    }

    private function rackPairingPriority(array $item, array $rack): int
    {
        $itemCategory = $this->pairingCategory($item);
        $rackCategories = collect($rack['cells'])
            ->filter()
            ->pluck('pairing_category')
            ->filter()
            ->unique();

        if ($rackCategories->isEmpty()) {
            return 10;
        }

        if ($itemCategory === self::PAIRING_WILBERT_VAULT) {
            if ($rackCategories->contains(self::PAIRING_WILBERT_VAULT)) {
                return 0;
            }

            return $rackCategories->contains(self::PAIRING_GARDEN_DOUBLE)
                ? self::PAIRING_AVOID
                : 10;
        }

        if ($itemCategory === self::PAIRING_GARDEN_DOUBLE) {
            return $rackCategories->contains(self::PAIRING_WILBERT_VAULT)
                ? self::PAIRING_AVOID
                : 0;
        }

        if ($itemCategory === self::PAIRING_LINER) {
            return $rackCategories->contains(self::PAIRING_GARDEN_DOUBLE) ? 0 : 10;
        }

        return $rackCategories->contains(self::PAIRING_GARDEN_DOUBLE)
            ? self::PAIRING_AVOID
            : 10;
    }

    private function pairingCategory(array $item): string
    {
        if (($item['placement_strategy'] ?? null) === LoadingProfile::PLACEMENT_FULL_TOP_SPLIT_BOTTOM_PAIR) {
            return self::PAIRING_GARDEN_DOUBLE;
        }

        if (in_array($item['loading_profile'] ?? null, [
            'regular_burial_vault',
            'regular_burial_vault_triune',
        ], true) || str_starts_with(mb_strtoupper((string) ($item['sku'] ?? '')), 'W3086-')) {
            return self::PAIRING_WILBERT_VAULT;
        }

        if (str_starts_with(mb_strtoupper((string) ($item['sku'] ?? '')), 'L')
            || (int) ($item['units_per_rack_position'] ?? 1) > 1) {
            return self::PAIRING_LINER;
        }

        return self::PAIRING_STANDARD_FILLER;
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
                ->filter(fn (int $index): bool => count($racks[$index]['stop_sequences']) === 1
                    && in_array($stopSequence, $racks[$index]['stop_sequences'], true))
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
