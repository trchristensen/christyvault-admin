<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\MaintenanceAsset;
use Illuminate\Database\Seeder;

class MaintenanceAssetImportSeeder extends Seeder
{
    public function run(): void
    {
        $colmaId = Location::query()->christyVault()->where('name', 'Christy Vault - Colma')->value('id');
        $tulareId = Location::query()->christyVault()->where('name', 'Christy Vault - Tulare')->value('id');

        if (! $colmaId || ! $tulareId) {
            throw new \RuntimeException('The Colma and Tulare Christy Vault plant locations are required.');
        }

        $assets = [
            ['8', '2007 Hyster H50FT Forklift', 'forklift', 2007, 'Hyster', 'H50FT', null, 'L177B12742D', $colmaId, null, 'hours', null],
            ['9', '2007 Hyster H50FT Forklift', 'forklift', 2007, 'Hyster', 'H50FT', null, 'L177B12745D', $colmaId, null, 'hours', null],
            ['11', '2003 Hyster H40XMS Forklift', 'forklift', 2003, 'Hyster', 'H40XMS', null, 'E001H02458A', $colmaId, null, 'hours', null],
            ['12', '2006 Hyster H50XL Forklift', 'forklift', 2006, 'Hyster', 'H50XL', null, 'L177B08694D', $colmaId, null, 'hours', null],
            ['18', '2023 Hyster H50XT Forklift', 'forklift', 2023, 'Hyster', 'H50XT', null, 'A380V15592X', $colmaId, null, 'hours', null],
            ['16', '2008 Hyster H50XL Forklift', 'forklift', 2008, 'Hyster', 'H50XL', null, 'L177V01883F', $colmaId, null, 'hours', null],
            ['22', '1999 John Deere 310E Backhoe', 'backhoe', 1999, 'John Deere', '310E', null, 'T0310EX879735', $colmaId, null, 'hours', null],
            ['26', '2023 Freightliner 2106 Boom Truck', 'boom_truck', 2023, 'Freightliner', '2106', '60923M3', '3ALHCYD24PDNZ3209', $colmaId, '2026-06-30', 'miles', null],
            ['29', '2013 Freightliner 2106 Boom Truck', 'boom_truck', 2013, 'Freightliner', '2106', '10919G1', '1FVHCYBSXDHBY0920', $colmaId, '2026-04-30', 'miles', null],
            ['53', '2006 Princeton PB50 Piggyback', 'piggyback_forklift', 2006, 'Princeton', 'PB50', null, '126434006', $colmaId, null, 'hours', null],
            ['61', '2006 Princeton PB50 Piggyback', 'piggyback_forklift', 2006, 'Princeton', 'PB50', null, '12663406', $colmaId, null, 'hours', null],
            ['65', '2007 Princeton PB50 Piggyback', 'piggyback_forklift', 2007, 'Princeton', 'PB50', null, '126641107', $colmaId, null, 'hours', null],
            ['74', '2008 Utility FB Trailer', 'trailer', 2008, 'Utility', 'FB', '4JM2955', '1UYFS23688A413601', $colmaId, null, null, null],
            ['76', '2008 Utility FB Trailer', 'trailer', 2008, 'Utility', 'FB', '4JM2954', '1UYFS236X8A413602', $colmaId, null, null, null],
            ['84', '2017 Freightliner CA125DC Tractor', 'tractor', 2017, 'Freightliner', 'CA125DC', 'WP86675', '3AKJGEDV1HSHG6702', $colmaId, '2026-02-28', 'miles', null],
            ['86', '2017 Freightliner CA125DC Tractor', 'tractor', 2017, 'Freightliner', 'CA125DC', 'WP86676', '3AKJGEDV3HSHG6703', $colmaId, '2026-02-28', 'miles', null],
            ['41', '2017 Princeton PB55.3 Piggyback', 'piggyback_forklift', 2017, 'Princeton', 'PB55.3', null, '4721505610', $colmaId, null, 'hours', null],
            ['71', '2018 Princeton PB55.3 Piggyback', 'piggyback_forklift', 2018, 'Princeton', 'PB55.3', null, '4810302280', $colmaId, null, 'hours', null],
            ['43', '2017 Freightliner CA125DC Tractor', 'tractor', 2017, 'Freightliner', 'CA125DC', '9F53116', '3AKJGEDVXHDPHP4743', $tulareId, '2026-05-31', 'miles', 'Tulare Location'],
        ];

        foreach ($assets as [$assetTag, $name, $category, $year, $manufacturer, $model, $licensePlate, $serialNumber, $locationId, $registrationExpiresOn, $meterType, $notes]) {
            MaintenanceAsset::updateOrCreate(
                ['asset_tag' => $assetTag],
                [
                    'name' => $name,
                    'category' => $category,
                    'year' => $year,
                    'manufacturer' => $manufacturer,
                    'model' => $model,
                    'license_plate' => $licensePlate,
                    'serial_number' => $serialNumber,
                    'location_id' => $locationId,
                    'registration_expires_on' => $registrationExpiresOn,
                    'meter_type' => $meterType,
                    'notes' => $notes,
                    'criticality' => 'medium',
                    'status' => 'operational',
                ],
            );
        }
    }
}
