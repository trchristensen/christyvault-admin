<?php

namespace App\Support;

use App\Models\VehicleConfiguration;
use Illuminate\Support\Collection;

final class TripPreTripChecklist
{
    public const VERSION = 'ca-property-cmv-2026-08-v3';

    public const RESPONSE_OK = 'ok';

    public const RESPONSE_DEFECT = 'defect';

    public const RESPONSE_NOT_APPLICABLE = 'not_applicable';

    public static function usesTrailer(?VehicleConfiguration $configuration): bool
    {
        return $configuration?->configuration_type === VehicleConfiguration::TYPE_RACK_TRAILER;
    }

    public static function usesPiggyback(?VehicleConfiguration $configuration): bool
    {
        return (bool) $configuration?->piggyback_forklift_onboard;
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, items: array<string, array{label: string, helper_text?: string, allow_not_applicable?: bool}>}>
     */
    public static function sections(?VehicleConfiguration $configuration): array
    {
        $sections = [
            [
                'key' => 'review',
                'label' => 'Review',
                'description' => 'Review the prior report and the combination before departure.',
                'items' => [
                    'prior_report' => [
                        'label' => 'Previous inspection report reviewed',
                        'helper_text' => 'If it listed safety defects, confirm they were repaired or certified as unnecessary before driving.',
                        'allow_not_applicable' => true,
                    ],
                    'general_condition' => [
                        'label' => 'Vehicle combination has no obvious unsafe condition',
                        'helper_text' => 'Walk around the selected equipment and check for damage, unsafe leaning, or leaks beneath it.',
                    ],
                ],
            ],
            [
                'key' => 'truck',
                'label' => 'Truck',
                'description' => 'Power unit, cab, brakes, running gear, and emergency equipment.',
                'items' => [
                    'truck_brakes_steering' => [
                        'label' => 'Brakes and steering operate normally',
                        'helper_text' => 'Service brake, parking brake, steering, and visible brake components. If equipped: air-pressure build and hold and low-air warning.',
                    ],
                    'truck_running_gear' => [
                        'label' => 'Tires, wheels, and suspension appear safe',
                        'helper_text' => 'Inflation, tread, visible tire damage, rims, lugs, hubs, suspension, and axles.',
                    ],
                    'truck_lights_visibility' => [
                        'label' => 'Lights, horn, and visibility equipment work',
                        'helper_text' => 'Headlights, signals, flashers, markers, reflectors, horn, windshield, mirrors, wipers, and washer.',
                    ],
                    'truck_cab_emergency' => [
                        'label' => 'Cab and emergency equipment are ready',
                        'helper_text' => 'Seat belt, gauges, ABS and warning indicators, charged secured fire extinguisher, three warning triangles, and required spare fuses if used.',
                    ],
                    'truck_leaks' => [
                        'label' => 'No apparent fluid or air leaks',
                        'helper_text' => 'Fuel, oil, coolant, hydraulic fluid, and air system.',
                    ],
                ],
            ],
        ];

        if (self::usesTrailer($configuration)) {
            $sections[] = [
                'key' => 'trailer',
                'label' => 'Trailer',
                'description' => 'Coupling, brakes, running gear, and trailer structure.',
                'items' => [
                    'trailer_coupling_connections' => [
                        'label' => 'Applicable coupling, locks, and connections are secure',
                        'helper_text' => 'Check the components this combination uses: fifth wheel and kingpin or pintle, safety latch, locking pins or chains, air hoses, and electrical cable.',
                    ],
                    'trailer_brakes_running_gear' => [
                        'label' => 'Trailer brakes and running gear appear safe',
                        'helper_text' => 'Service and parking/emergency brakes, ABS indicator, tires, wheels, rims, lugs, hubs, suspension, and axles.',
                    ],
                    'trailer_structure_lights' => [
                        'label' => 'Trailer structure and required lights are secure',
                        'helper_text' => 'Lights, signals, markers, reflectors, landing gear, frame, deck, racks, doors, and compartments.',
                    ],
                ],
            ];
        }

        $sections[] = [
            'key' => 'load',
            'label' => 'Load',
            'description' => 'Verify weight, placement, and cargo securement for this trip.',
            'items' => [
                'load_plan_weight_distribution' => [
                    'label' => 'Load matches the load summary and appears balanced',
                    'helper_text' => 'Confirm the products and placement match the generated load summary. You are not being asked to recalculate the weight.',
                ],
                'load_securement' => [
                    'label' => 'Cargo and securement equipment are ready for travel',
                    'helper_text' => 'Racks, pins, locks, straps, chains, tie-downs, blocking, and their visible condition.',
                ],
                'load_loose_items_visibility' => [
                    'label' => 'Nothing can shift, fall, or obstruct safe operation',
                    'helper_text' => 'Check pallets and loose equipment, lights, mirrors, plates, controls, driver visibility, and emergency access.',
                ],
            ],
        ];

        if (self::usesPiggyback($configuration)) {
            $sections[] = [
                'key' => 'piggyback',
                'label' => 'Piggyback',
                'description' => 'Trailer attachment and beginning-of-shift forklift condition.',
                'items' => [
                    'piggyback_mount_transport' => [
                        'label' => 'Piggyback mounting and transport position are secure',
                        'helper_text' => 'Mounting points, hooks, locks, pins, latches, chains, forks, mast, attachments, and required lights or reflectors.',
                    ],
                    'piggyback_operating_condition' => [
                        'label' => 'Beginning-of-shift forklift check is satisfactory',
                        'helper_text' => 'Complete before first operating it today: leaks, tires, wheels, brakes, steering, horn, lights, restraint, fuel or battery, forks, chains, cables, hoses, mast, and lift controls.',
                    ],
                ],
            ];
        }

        return $sections;
    }

    /** @return Collection<string, array{label: string, helper_text?: string, allow_not_applicable?: bool}> */
    public static function items(?VehicleConfiguration $configuration): Collection
    {
        return collect(self::sections($configuration))
            ->flatMap(fn (array $section): array => $section['items']);
    }

    public static function certificationText(bool $safeToOperate): string
    {
        return $safeToOperate
            ? 'I certify that I inspected the identified vehicle combination, recorded the results truthfully, and found it safe to operate at the time of inspection.'
            : 'I certify that I inspected the identified vehicle combination, recorded the results truthfully, reported the listed issues, and will follow the carrier’s operating decision before using equipment that may be unsafe.';
    }
}
