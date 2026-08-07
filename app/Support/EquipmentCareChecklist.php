<?php

namespace App\Support;

use App\Models\MaintenanceAsset;
use Illuminate\Support\Collection;

final class EquipmentCareChecklist
{
    public const VERSION = 'optional-equipment-care-2026-08-v1';

    public const SUPPORTED_CATEGORIES = [
        'tractor',
        'truck',
        'boom_truck',
        'trailer',
        'piggyback_forklift',
    ];

    /**
     * @return Collection<string, array{label: string, description: string}>
     */
    public static function items(MaintenanceAsset|string|null $asset): Collection
    {
        $category = $asset instanceof MaintenanceAsset ? $asset->category : $asset;

        $items = match ($category) {
            'tractor', 'truck', 'boom_truck' => [
                'engine_oil_level' => [
                    'label' => 'Checked the engine oil level',
                    'description' => 'Look for a level below or above the safe marks, an unexpected change since the last check, unusual oil appearance, or fresh oil around the engine or beneath the truck. Check on level ground using the manufacturer’s procedure.',
                ],
                'coolant_level' => [
                    'label' => 'Checked the coolant recovery-tank level',
                    'description' => 'Look for coolant below the appropriate cold mark, wet hoses or connections, dried coolant residue, damaged hoses, or a level that repeatedly needs topping off. Never remove a hot pressurized cooling-system cap.',
                ],
                'steering_washer_fluids' => [
                    'label' => 'Checked power-steering and washer fluid',
                    'description' => 'Look for low power-steering fluid, foaming, wet hoses or pump areas, steering that feels different, and low washer fluid. Use only approved fluid if you are authorized to add it.',
                ],
                'tire_pressures' => [
                    'label' => 'Checked cold tire pressures with a gauge',
                    'description' => 'Look for any tire below its vehicle-specific target, a large difference between paired tires, or a tire that keeps losing pressure. Use the placard or manufacturer target and do not bleed pressure from a hot tire.',
                ],
                'tires_wheels' => [
                    'label' => 'Looked over tires, wheels, valve stems, and lug nuts',
                    'description' => 'Look for cuts, bulges, tread separation, exposed material, embedded objects, uneven wear, cracked valve stems, damaged rims, missing or loose hardware, rust trails around lug nuts, or leaking hub seals.',
                ],
                'manual_air_tanks' => [
                    'label' => 'Drained the manual air tanks',
                    'description' => 'Look for excessive water or oil, a drain that will not open or close, or a damaged pull cable or valve. This is an end-of-day task only when the vehicle has manual drains; follow its procedure and leave unchecked for automatic drains.',
                ],
                'visibility_cleaning' => [
                    'label' => 'Cleaned windows, mirrors, lights, and reflectors',
                    'description' => 'Look for dirt, concrete dust, streaks, haze, damaged lenses, loose mirrors, or anything blocking the driver’s view or required lighting and reflectors.',
                ],
                'emergency_equipment' => [
                    'label' => 'Checked and organized emergency equipment',
                    'description' => 'Look for a missing, loose, discharged, damaged, or expired fire extinguisher; missing warning triangles; damaged storage boxes; or other required supplies that are not secured and ready.',
                ],
            ],
            'trailer' => [
                'tire_pressures' => [
                    'label' => 'Checked cold tire pressures with a gauge',
                    'description' => 'Look for any tire below its trailer-specific target, a large difference between dual or paired tires, or a tire that keeps losing pressure. Do not bleed pressure from a hot tire.',
                ],
                'tires_wheels' => [
                    'label' => 'Looked over tires, wheels, valve stems, and lug nuts',
                    'description' => 'Look for cuts, bulges, tread separation, exposed material, embedded objects, uneven wear, cracked valve stems, damaged rims, missing or loose hardware, rust trails around lug nuts, or leaking hub seals.',
                ],
                'manual_air_tanks' => [
                    'label' => 'Drained the manual trailer air tanks',
                    'description' => 'Look for excessive water or oil, a drain that will not open or close, or a damaged pull cable or valve. Use the trailer procedure and leave unchecked for automatic drains or when this does not apply.',
                ],
                'air_electrical_connections' => [
                    'label' => 'Cleaned and checked air and electrical connections',
                    'description' => 'Look for cracked or missing glad-hand seals, audible leaks, chafed or kinked lines, damaged plugs, corrosion, dirt in connections, or missing protection when disconnected.',
                ],
                'deck_racks_locks' => [
                    'label' => 'Cleared and checked the deck, racks, pins, and locks',
                    'description' => 'Look for loose debris, damaged deck areas, bent or cracked racks, missing pins, locks that do not fully engage, and components that bind or are unusually difficult to operate.',
                ],
                'lights_reflectors' => [
                    'label' => 'Cleaned lights, reflectors, and conspicuity tape',
                    'description' => 'Look for dirt, concrete dust, cracked lenses, moisture inside lamps, dim or failed lights, loose wiring, missing reflectors, or torn and obscured conspicuity tape.',
                ],
                'securement_equipment' => [
                    'label' => 'Organized and inspected securement equipment',
                    'description' => 'Look for cut or frayed straps, stretched or damaged chain links, bent binders or pins, missing identification tags, damaged hooks, and equipment stored where it could shift or fall.',
                ],
            ],
            'piggyback_forklift' => [
                'fluid_levels' => [
                    'label' => 'Checked approved fluid levels',
                    'description' => 'Look for low engine oil, coolant, or hydraulic fluid; fresh leaks; wet hoses and fittings; damaged hoses; or a level that repeatedly needs topping off. Use the manufacturer’s safe procedure.',
                ],
                'tire_pressures' => [
                    'label' => 'Checked tire pressures or solid-tire condition',
                    'description' => 'For pneumatic tires, look for pressure below the manufacturer target, recurring pressure loss, cuts, or valve damage. For solid tires, look for separation, missing chunks, deep cuts, or serious uneven wear.',
                ],
                'radiator_debris' => [
                    'label' => 'Cleared loose debris from cooling and intake areas',
                    'description' => 'Look for leaves, concrete dust, plastic, or other material restricting airflow, plus damaged screens or signs of overheating. Work only with the equipment shut down and secured as instructed.',
                ],
                'forks_mast_chains' => [
                    'label' => 'Cleaned and looked over forks, mast, chains, and hoses',
                    'description' => 'Look for fork cracks or bends, unequal fork height, damaged retainers, chain damage or uneven tension, hydraulic leaks, worn rollers, and cracked, rubbing, or blistered hoses.',
                ],
                'operator_area' => [
                    'label' => 'Cleaned the operator area and safety equipment',
                    'description' => 'Look for slippery or obstructed steps and floor areas, damaged restraints, sticking controls, unreadable labels, loose items, and lights, horn, alarm, or other warning devices that do not work.',
                ],
            ],
            default => [],
        };

        if ($category === 'boom_truck') {
            $items['hydraulic_boom_condition'] = [
                'label' => 'Looked over the boom hydraulic system and controls',
                'description' => 'Look for fresh hydraulic leaks, wet or damaged hoses, cracked fittings, abrasion, loose or missing retainers, unusual boom movement or noise, damaged controls, and debris around moving components.',
            ];
        }

        return collect($items);
    }
}
