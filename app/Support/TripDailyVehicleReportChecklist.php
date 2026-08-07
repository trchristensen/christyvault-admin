<?php

namespace App\Support;

final class TripDailyVehicleReportChecklist
{
    public const VERSION = 'ca-dvir-2026-08-v2';

    /**
     * @return array<string, array{label: string, helper_text: string}>
     */
    public static function items(): array
    {
        return [
            'brakes_steering_coupling' => [
                'label' => 'Brakes, steering, and coupling remained in proper working order',
                'helper_text' => 'Service and parking brakes, trailer brake connections, steering, and the applicable coupling devices.',
            ],
            'tires_wheels_running_gear' => [
                'label' => 'Tires, wheels, rims, and running gear had no new problem',
                'helper_text' => 'Include damage, inflation, unusual vibration, wheel or rim concerns, suspension, and visible leaks.',
            ],
            'lights_visibility_emergency' => [
                'label' => 'Lights, reflectors, horn, mirrors, wipers, and emergency equipment remained ready',
                'helper_text' => 'Report anything that stopped working or was damaged or missing during the day.',
            ],
            'load_body_piggyback' => [
                'label' => 'Load, securement, trailer structure, and piggyback had no new problem',
                'helper_text' => 'Include shifted cargo, straps or locks, racks, trailer structure, piggyback mounting, or any mechanical issue noticed during operation.',
            ],
        ];
    }
}
