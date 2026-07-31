<?php

use Carbon\Carbon;

it('prints each rack pallet on its own compact line', function (): void {
    $trip = new class
    {
        public string $trip_number = 'TRIP-00089';

        public Carbon $scheduled_date;

        public function __construct()
        {
            $this->scheduled_date = Carbon::parse('2026-08-03');
        }

        public function orderedDeliveryOrders()
        {
            return collect([
                ['order_number' => 'ORD-02245'],
                ['order_number' => 'ORD-02246'],
            ]);
        }
    };

    $result = [
        'summary' => [
            'maximum_product_weight_lbs' => 38_500,
            'known_weight_lbs' => 27_012,
            'product_units' => 43,
        ],
        'vehicle_configuration' => [
            'name' => 'Rack trailer — forklift onboard',
            'piggyback_forklift_onboard' => true,
        ],
        'stops' => [
            [
                'sequence' => 1,
                'location_name' => 'Mt. Olivet',
                'order_number' => 'ORD-02245',
                'order_id' => 1,
            ],
            [
                'sequence' => 2,
                'location_name' => 'Sunset View Cemetery',
                'order_number' => 'ORD-02246',
                'order_id' => 2,
            ],
        ],
        'warnings' => [],
        'ready_for_automatic_placement' => true,
    ];
    $diagram = [
        'available' => true,
        'unplaced' => [],
        'racks' => [[
            'number' => 6,
            'type_code' => 'standard_3_high',
            'level_count' => 3,
            'product_weight_lbs' => 2664,
            'has_unknown_weight' => false,
            'cells' => [[
                'code' => '3×G2412V23.7 · 3×G2412V2',
                'is_pallet_level' => true,
                'stop_sequence' => 1,
                'pallets' => [
                    ['code' => '3×G2412V23.7'],
                    ['code' => '3×G2412V2'],
                ],
            ], null, null],
        ]],
        'flatbed_pallet_capacity' => 0,
        'flatbed_pallets' => [],
        'legend' => [],
        'non_rack_cargo' => [],
    ];

    $html = view('filament.resources.trip-resource.load-summary-print-sheet', [
        'trip' => $trip,
        'result' => $result,
        'diagram' => $diagram,
        'fillAllocations' => [],
    ])->render();

    expect($html)
        ->toContain('cv-print-rack-cell-pallet-multiple')
        ->toContain('<span class="cv-print-pallet-line">3×G2412V23.7</span>')
        ->toContain('<span class="cv-print-pallet-line">3×G2412V2</span>')
        ->not->toContain('<span>3×G2412V23.7 · 3×G2412V2</span>');
});
