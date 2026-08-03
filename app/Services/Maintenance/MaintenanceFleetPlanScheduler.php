<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceFleetPlan;
use App\Models\MaintenanceFleetPlanAsset;
use App\Models\MaintenanceFleetServiceRun;
use App\Models\MaintenanceWorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class MaintenanceFleetPlanScheduler
{
    public function generateDue(?int $assetId = null): int
    {
        $generatedWorkOrders = 0;

        MaintenanceFleetPlan::query()
            ->where('active', true)
            ->orderBy('id')
            ->each(function (MaintenanceFleetPlan $plan) use ($assetId, &$generatedWorkOrders): void {
                $this->syncMatchingAssets($plan);

                if ($assetId !== null && ! $plan->members()->where('asset_id', $assetId)->where('matches_filter', true)->exists()) {
                    return;
                }

                $run = $this->generate($plan);
                $generatedWorkOrders += $run?->workOrders()->count() ?? 0;
            });

        return $generatedWorkOrders;
    }

    public function syncMatchingAssets(MaintenanceFleetPlan $plan): void
    {
        $matchingAssets = $plan->matchingAssetsQuery()->get(['id', 'current_meter']);
        $matchingIds = $matchingAssets->pluck('id');

        $plan->members()->whereNotIn('asset_id', $matchingIds)->update(['matches_filter' => false]);

        foreach ($matchingAssets as $asset) {
            $member = $plan->members()->firstOrCreate(
                ['asset_id' => $asset->id],
                [
                    'included' => true,
                    'matches_filter' => true,
                    'baseline_meter' => $asset->current_meter,
                    'next_due_meter' => $asset->current_meter !== null
                        ? (float) $asset->current_meter + (float) $plan->meter_interval
                        : null,
                ],
            );

            $updates = ['matches_filter' => true];

            if ($member->baseline_meter === null && $member->next_due_meter === null && $asset->current_meter !== null) {
                $updates['baseline_meter'] = $asset->current_meter;
                $updates['next_due_meter'] = (float) $asset->current_meter + (float) $plan->meter_interval;
            }

            $member->update($updates);
        }
    }

    public function generate(MaintenanceFleetPlan $plan, bool $force = false): ?MaintenanceFleetServiceRun
    {
        return DB::transaction(function () use ($plan, $force): ?MaintenanceFleetServiceRun {
            $plan = MaintenanceFleetPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $this->syncMatchingAssets($plan);

            if ($plan->serviceRuns()->where('status', 'open')->exists()) {
                return null;
            }

            $members = $this->includedMembersQuery($plan)->with('asset')->get();
            $trigger = $members->first(fn (MaintenanceFleetPlanAsset $member): bool => $member->next_due_meter !== null
                && $member->asset?->current_meter !== null
                && (float) $member->asset->current_meter >= (float) $member->next_due_meter);

            if (! $force && ! $trigger) {
                return null;
            }

            if ($members->isEmpty()) {
                return null;
            }

            $run = $plan->serviceRuns()->create([
                'triggered_by_asset_id' => $trigger?->asset_id,
                'status' => 'open',
                'generated_at' => now(),
            ]);

            foreach ($members as $member) {
                $this->createWorkOrder($run, $member, $trigger);
            }

            $plan->update(['last_generated_at' => now()]);

            return $run->load('workOrders');
        });
    }

    public function handleWorkOrderStatusChange(MaintenanceWorkOrder $workOrder): void
    {
        if (! $workOrder->fleet_service_run_id || ! $workOrder->wasChanged('status')) {
            return;
        }

        DB::transaction(function () use ($workOrder): void {
            $run = MaintenanceFleetServiceRun::query()
                ->lockForUpdate()
                ->with('plan')
                ->find($workOrder->fleet_service_run_id);

            if (! $run || $run->status !== 'open') {
                return;
            }

            $currentMeter = $workOrder->asset()->value('current_meter');

            if ($workOrder->status === 'completed' && $currentMeter !== null) {
                $currentMeter = (float) $currentMeter;

                $run->plan->members()->where('asset_id', $workOrder->asset_id)->update([
                    'baseline_meter' => $currentMeter,
                    'next_due_meter' => $currentMeter + (float) $run->plan->meter_interval,
                    'last_serviced_at' => $workOrder->verified_at ?? now(),
                ]);
            }

            $unfinished = $run->workOrders()
                ->whereNotIn('status', ['completed', 'canceled'])
                ->exists();

            if (! $unfinished) {
                $run->update(['status' => 'completed', 'completed_at' => now()]);
                $run->plan->update(['last_completed_at' => now()]);
            }
        });
    }

    private function includedMembersQuery(MaintenanceFleetPlan $plan): HasMany
    {
        return $plan->members()
            ->where('included', true)
            ->where('matches_filter', true)
            ->whereHas('asset', fn (Builder $query): Builder => $query->where('status', '!=', 'retired'))
            ->orderBy('asset_id');
    }

    private function createWorkOrder(
        MaintenanceFleetServiceRun $run,
        MaintenanceFleetPlanAsset $member,
        ?MaintenanceFleetPlanAsset $trigger,
    ): void {
        $plan = $run->plan;
        $vendor = $plan->maintenanceVendor;
        $triggerText = $trigger?->asset
            ? "Triggered by {$trigger->asset->display_name} reaching its fleet-service threshold."
            : 'Fleet service was generated manually.';
        $contact = $plan->serviceContactSummary();
        $description = collect([$plan->description, $triggerText, $contact])->filter()->join("\n\n");
        $checklist = collect($plan->checklist ?? [])->map(function ($item): array {
            $label = is_array($item) ? ($item['task'] ?? $item['label'] ?? '') : (string) $item;

            return ['task' => $label, 'completed' => false, 'notes' => null];
        })->filter(fn (array $item): bool => filled($item['task']))->values()->all();

        MaintenanceWorkOrder::create([
            'asset_id' => $member->asset_id,
            'fleet_service_run_id' => $run->id,
            'assigned_to_user_id' => $plan->default_assignee_id,
            'maintenance_vendor_id' => $vendor?->id,
            'service_provider' => $vendor?->name ?? $plan->service_provider,
            'service_contact_name' => $vendor?->contact_person ?? $plan->service_contact_name,
            'service_phone' => $vendor?->phone ?? $plan->service_phone,
            'title' => $plan->name,
            'description' => $description,
            'type' => 'preventive',
            'priority' => $plan->priority,
            'status' => 'approved',
            'checklist' => $checklist,
        ]);
    }
}
