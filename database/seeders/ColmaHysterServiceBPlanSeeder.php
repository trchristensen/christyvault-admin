<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\MaintenanceFleetPlan;
use App\Services\Maintenance\MaintenanceFleetPlanScheduler;
use Illuminate\Database\Seeder;

class ColmaHysterServiceBPlanSeeder extends Seeder
{
    public function run(): void
    {
        $colmaId = Location::query()
            ->christyVault()
            ->where('name', 'Christy Vault - Colma')
            ->value('id');

        if (! $colmaId) {
            throw new \RuntimeException('The Christy Vault - Colma plant location is required.');
        }

        $plan = MaintenanceFleetPlan::firstOrCreate(
            [
                'location_id' => $colmaId,
                'name' => 'Papé Service B — Colma Hyster Forklifts',
            ],
            [
                'description' => 'When any included Colma Hyster forklift accumulates 250 hours after the last group service, request Service B from Papé for every included forklift.',
                'manufacturer' => 'Hyster',
                'asset_category' => 'forklift',
                'meter_type' => 'hours',
                'meter_interval' => 250,
                'service_provider' => 'Papé',
                'service_contact_name' => 'Brenda',
                'service_phone' => '510-661-5700',
                'priority' => 'normal',
                'active' => true,
                'checklist' => [
                    ['task' => 'Confirm current hour-meter readings for every included forklift.'],
                    ['task' => 'Call Papé and request Service B for all included Colma Hyster forklifts.'],
                    ['task' => 'Provide Papé with every included unit number.'],
                    ['task' => 'Confirm the appointment date and expected downtime.'],
                    ['task' => 'Verify Service B was completed on this unit.'],
                    ['task' => 'Record this unit’s meter reading at service completion.'],
                    ['task' => 'Attach Papé’s service report or invoice.'],
                    ['task' => 'Create corrective work orders for any recommended repairs.'],
                ],
            ],
        );

        app(MaintenanceFleetPlanScheduler::class)->syncMatchingAssets($plan);

        $serviceBaselines = [
            '8' => 5708,
            '9' => 8281,
            '12' => 7878,
            '16' => 8207,
        ];

        foreach ($serviceBaselines as $assetTag => $baseline) {
            $member = $plan->members()->whereHas('asset', fn ($query) => $query->where('asset_tag', $assetTag))->first();

            if ($member && $member->baseline_meter === null) {
                $member->update([
                    'baseline_meter' => $baseline,
                    'next_due_meter' => $baseline + (float) $plan->meter_interval,
                    'last_serviced_at' => '2026-07-22 12:00:00',
                ]);
            }
        }
    }
}
