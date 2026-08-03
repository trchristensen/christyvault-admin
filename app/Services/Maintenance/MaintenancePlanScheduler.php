<?php

namespace App\Services\Maintenance;

use App\Models\MaintenancePlan;
use App\Models\MaintenanceWorkOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MaintenancePlanScheduler
{
    public function generateDue(?int $assetId = null): int
    {
        $generated = 0;

        MaintenancePlan::query()
            ->with(['asset', 'defaultAssignee'])
            ->where('active', true)
            ->when($assetId, fn (Builder $query) => $query->where('asset_id', $assetId))
            ->orderBy('id')
            ->each(function (MaintenancePlan $plan) use (&$generated): void {
                if ($this->isDue($plan) && $this->generate($plan)) {
                    $generated++;
                }
            });

        return $generated;
    }

    public function isDue(MaintenancePlan $plan): bool
    {
        if ($plan->trigger_type === 'meter') {
            return $plan->next_due_meter !== null
                && $plan->asset?->current_meter !== null
                && (float) $plan->asset->current_meter >= (float) $plan->next_due_meter;
        }

        return $plan->next_due_date !== null
            && $plan->next_due_date->lte(today()->addDays($plan->lead_days));
    }

    public function generate(MaintenancePlan $plan): ?MaintenanceWorkOrder
    {
        return DB::transaction(function () use ($plan): ?MaintenanceWorkOrder {
            $plan = MaintenancePlan::query()->lockForUpdate()->with('asset')->findOrFail($plan->id);

            if (! $this->isDue($plan) || $plan->workOrders()->open()->exists()) {
                return null;
            }

            $dueAt = $plan->trigger_type === 'calendar' ? $plan->next_due_date?->endOfDay() : null;
            $checklist = collect($plan->checklist ?? [])->map(function ($item): array {
                $label = is_array($item) ? ($item['task'] ?? $item['label'] ?? '') : (string) $item;

                return ['task' => $label, 'completed' => false, 'notes' => null];
            })->filter(fn (array $item): bool => filled($item['task']))->values()->all();

            $workOrder = MaintenanceWorkOrder::create([
                'asset_id' => $plan->asset_id,
                'plan_id' => $plan->id,
                'assigned_to_user_id' => $plan->default_assignee_id,
                'title' => $plan->name,
                'description' => $plan->description,
                'type' => 'preventive',
                'priority' => $plan->priority,
                'status' => $dueAt?->isFuture() ? 'scheduled' : 'approved',
                'scheduled_at' => $dueAt,
                'due_at' => $dueAt,
                'checklist' => $checklist,
            ]);

            $this->advance($plan);
            $plan->update(['last_generated_at' => now()]);

            return $workOrder;
        });
    }

    private function advance(MaintenancePlan $plan): void
    {
        if ($plan->trigger_type === 'meter') {
            $next = (float) $plan->next_due_meter;
            $interval = max(0.01, (float) $plan->meter_interval);
            $current = (float) ($plan->asset?->current_meter ?? $next);

            do {
                $next += $interval;
            } while ($next <= $current);

            $plan->next_due_meter = $next;

            return;
        }

        $next = $plan->next_due_date?->copy() ?? today();

        do {
            $next = $this->addInterval($next, $plan->interval_value, $plan->interval_unit ?? 'months');
        } while ($next->lte(today()));

        $plan->next_due_date = $next;
    }

    private function addInterval(CarbonInterface $date, int $value, string $unit): CarbonInterface
    {
        return match ($unit) {
            'days' => $date->addDays($value),
            'weeks' => $date->addWeeks($value),
            'years' => $date->addYearsNoOverflow($value),
            default => $date->addMonthsNoOverflow($value),
        };
    }
}
