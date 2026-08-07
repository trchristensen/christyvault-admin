<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceAsset;
use App\Models\MaintenanceRequest;
use App\Models\TripPreTripInspection;
use App\Models\TripPreTripInspectionDefect;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VehicleInspectionReportService
{
    /**
     * @param  array<string, MaintenanceAsset|null>  $assets
     */
    public function attachAssets(TripPreTripInspection $inspection, array $assets): void
    {
        foreach ($assets as $role => $asset) {
            if (! $asset) {
                continue;
            }

            $inspection->assets()->attach($asset->getKey(), [
                'role' => $role,
                'asset_snapshot' => json_encode($this->assetSnapshot($asset)),
            ]);
        }
    }

    /**
     * @param  array<int, array{component_key: string, component_label: string, description: string, asset: MaintenanceAsset|null, driver_assessment?: string}>  $entries
     */
    public function createDefects(TripPreTripInspection $inspection, array $entries): Collection
    {
        return DB::transaction(function () use ($inspection, $entries): Collection {
            $created = collect();

            collect($entries)
                ->groupBy(fn (array $entry): string => (string) ($entry['asset']?->getKey() ?? 'unassigned'))
                ->each(function (Collection $assetEntries) use ($inspection, $created): void {
                    /** @var MaintenanceAsset|null $asset */
                    $asset = $assetEntries->first()['asset'];
                    $request = $this->createMaintenanceRequest($inspection, $asset, $assetEntries);

                    foreach ($assetEntries as $entry) {
                        $created->push($inspection->inspectionDefects()->create([
                            'maintenance_asset_id' => $asset?->getKey(),
                            'maintenance_request_id' => $request?->getKey(),
                            'component_key' => $entry['component_key'],
                            'component_label' => $entry['component_label'],
                            'description' => $entry['description'],
                            'safety_related' => true,
                            'driver_assessment' => $entry['driver_assessment'] ?? TripPreTripInspectionDefect::DRIVER_ASSESSMENT_REVIEW,
                            'status' => TripPreTripInspectionDefect::STATUS_OPEN,
                            'reported_at' => $inspection->completed_at,
                        ]));
                    }

                    if ($asset && $asset->status !== 'retired') {
                        $requiresStop = $assetEntries->contains(
                            fn (array $entry): bool => ($entry['driver_assessment'] ?? null) === TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP,
                        );

                        if ($requiresStop) {
                            $asset->update(['status' => 'out_of_service']);
                        } elseif ($asset->status === 'operational') {
                            $asset->update(['status' => 'restricted']);
                        }
                    }
                });

            return $created;
        });
    }

    public function linkRequestWorkOrder(MaintenanceRequest $request): void
    {
        if (! $request->workOrder) {
            return;
        }

        $request->vehicleInspectionDefects()
            ->whereNull('maintenance_work_order_id')
            ->update(['maintenance_work_order_id' => $request->workOrder->getKey()]);
    }

    public function certifyResolution(
        TripPreTripInspectionDefect $defect,
        string $status,
        string $notes,
        int $userId,
    ): void {
        DB::transaction(function () use ($defect, $status, $notes, $userId): void {
            $defect->update([
                'status' => $status,
                'operating_decision' => TripPreTripInspectionDefect::OPERATING_DECISION_MAY_OPERATE,
                'reviewed_by_user_id' => $userId,
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'resolved_by_user_id' => $userId,
                'resolved_at' => now(),
                'resolution_notes' => $notes,
                'resolution_certification' => $status === TripPreTripInspectionDefect::STATUS_CORRECTED
                    ? 'The defect was corrected and the repair was reviewed before the vehicle was returned to service.'
                    : 'The reported condition was reviewed and correction was determined not to be necessary for safe operation.',
            ]);

            $asset = $defect->asset;

            if ($asset
                && $asset->status !== 'retired'
                && ! $asset->vehicleInspectionDefects()->where('status', TripPreTripInspectionDefect::STATUS_OPEN)->exists()) {
                $asset->update(['status' => 'operational']);
            }
        });
    }

    public function requireRepairBeforeOperation(
        TripPreTripInspectionDefect $defect,
        string $notes,
        int $userId,
    ): void {
        DB::transaction(function () use ($defect, $notes, $userId): void {
            $defect->update([
                'operating_decision' => TripPreTripInspectionDefect::OPERATING_DECISION_OUT_OF_SERVICE,
                'reviewed_by_user_id' => $userId,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            if ($defect->asset && $defect->asset->status !== 'retired') {
                $defect->asset->update(['status' => 'out_of_service']);
            }
        });
    }

    private function createMaintenanceRequest(
        TripPreTripInspection $inspection,
        ?MaintenanceAsset $asset,
        Collection $entries,
    ): ?MaintenanceRequest {
        if (! $asset) {
            return null;
        }

        $reportContext = $inspection->trip?->trip_number ?? $inspection->report_type_label;
        $details = $entries
            ->map(fn (array $entry): string => "- {$entry['component_label']}: {$entry['description']}")
            ->join("\n");

        $requiresStop = $entries->contains(
            fn (array $entry): bool => ($entry['driver_assessment'] ?? null) === TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP,
        );

        return MaintenanceRequest::query()->create([
            'asset_id' => $asset->getKey(),
            'location_id' => $asset->location_id,
            'requested_by_user_id' => $inspection->user_id,
            'requester_name' => $inspection->driver_name,
            'title' => ($inspection->report_type === TripPreTripInspection::TYPE_EQUIPMENT_CARE
                ? "Equipment care issue — {$asset->asset_tag}"
                : "Vehicle inspection issue — {$asset->asset_tag}"),
            'description' => "Reported on {$reportContext}.\n\n{$details}",
            'priority' => $requiresStop ? 'urgent' : 'high',
            'safety_related' => true,
            'status' => 'new',
            'submitted_at' => $inspection->completed_at,
        ]);
    }

    /** @return array<string, mixed> */
    public function assetSnapshot(MaintenanceAsset $asset): array
    {
        return [
            'id' => $asset->getKey(),
            'asset_tag' => $asset->asset_tag,
            'name' => $asset->name,
            'category' => $asset->category,
            'status' => $asset->status,
            'license_plate' => $asset->license_plate,
            'serial_number' => $asset->serial_number,
        ];
    }
}
