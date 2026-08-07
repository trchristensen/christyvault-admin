<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceWorkOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number', 'asset_id', 'request_id', 'plan_id', 'fleet_service_run_id', 'assigned_to_user_id', 'maintenance_vendor_id',
        'service_provider', 'service_contact_name', 'service_phone', 'vendor_reference',
        'purchase_order_number', 'authorization_limit', 'created_by_user_id',
        'verified_by_user_id', 'title', 'description', 'type', 'priority', 'status', 'safety_related',
        'estimated_hours', 'scheduled_at', 'due_at', 'started_at', 'completed_at', 'verified_at',
        'downtime_started_at', 'downtime_ended_at', 'downtime_minutes', 'checklist', 'findings',
        'work_performed', 'completion_notes', 'attachment_paths',
    ];

    protected function casts(): array
    {
        return [
            'safety_related' => 'boolean',
            'estimated_hours' => 'decimal:2',
            'authorization_limit' => 'decimal:2',
            'scheduled_at' => 'datetime',
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'downtime_started_at' => 'datetime',
            'downtime_ended_at' => 'datetime',
            'checklist' => 'array',
            'attachment_paths' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $workOrder): void {
            if ($workOrder->isDirty('maintenance_vendor_id') && $workOrder->maintenance_vendor_id) {
                $vendor = MaintenanceVendor::find($workOrder->maintenance_vendor_id);

                if ($vendor) {
                    foreach ($vendor->snapshot() as $field => $value) {
                        if (blank($workOrder->{$field}) || ($workOrder->exists && ! $workOrder->isDirty($field))) {
                            $workOrder->{$field} = $value;
                        }
                    }
                }
            }
        });

        static::created(function (self $workOrder): void {
            if ($workOrder->number === null) {
                $workOrder->updateQuietly(['number' => sprintf('MWO-%06d', $workOrder->id)]);
            }
        });
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAsset::class, 'asset_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'request_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'plan_id');
    }

    public function fleetServiceRun(): BelongsTo
    {
        return $this->belongsTo(MaintenanceFleetServiceRun::class, 'fleet_service_run_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function maintenanceVendor(): BelongsTo
    {
        return $this->belongsTo(MaintenanceVendor::class, 'maintenance_vendor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function laborEntries(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrderLaborEntry::class, 'work_order_id');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrderPart::class, 'work_order_id');
    }

    public function vehicleInspectionDefects(): HasMany
    {
        return $this->hasMany(TripPreTripInspectionDefect::class, 'maintenance_work_order_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['completed', 'canceled']);
    }

    public function getLaborHoursAttribute(): float
    {
        return round(((int) $this->laborEntries()->sum('minutes')) / 60, 2);
    }

    public function getPartsCostAttribute(): float
    {
        return (float) $this->parts()->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as total')->value('total');
    }

    public function start(?User $user = null): void
    {
        $this->update(['status' => 'in_progress', 'started_at' => $this->started_at ?? now()]);

        if (! $this->laborEntries()->whereNull('ended_at')->exists()) {
            $this->laborEntries()->create(['user_id' => $user?->id, 'started_at' => now()]);
        }
    }

    public function pause(string $status = 'on_hold'): void
    {
        $this->laborEntries()->whereNull('ended_at')->get()->each->stop();
        $this->update(['status' => $status]);
    }

    public function complete(?User $user = null): void
    {
        $this->laborEntries()->whereNull('ended_at')->get()->each->stop();
        $this->update([
            'status' => 'pending_verification',
            'completed_at' => now(),
            'assigned_to_user_id' => $this->assigned_to_user_id ?? $user?->id,
        ]);
    }

    public function verify(User $user): void
    {
        $downtimeMinutes = $this->downtime_started_at
            ? $this->downtime_started_at->diffInMinutes($this->downtime_ended_at ?? now())
            : $this->downtime_minutes;

        $this->update([
            'status' => 'completed',
            'verified_by_user_id' => $user->id,
            'verified_at' => now(),
            'downtime_ended_at' => $this->downtime_started_at ? ($this->downtime_ended_at ?? now()) : null,
            'downtime_minutes' => $downtimeMinutes,
        ]);

        $this->vehicleInspectionDefects()
            ->where('status', TripPreTripInspectionDefect::STATUS_OPEN)
            ->get()
            ->each(fn (TripPreTripInspectionDefect $defect) => $defect->update([
                'status' => TripPreTripInspectionDefect::STATUS_CORRECTED,
                'operating_decision' => TripPreTripInspectionDefect::OPERATING_DECISION_MAY_OPERATE,
                'reviewed_by_user_id' => $user->getKey(),
                'reviewed_at' => now(),
                'review_notes' => $this->work_performed ?: $this->completion_notes,
                'resolved_by_user_id' => $user->getKey(),
                'resolved_at' => now(),
                'resolution_notes' => $this->work_performed ?: $this->completion_notes,
                'resolution_certification' => "Corrected under {$this->number} and verified by {$user->name}.",
            ]));

        if ($this->asset
            && $this->asset->status !== 'retired'
            && ! $this->asset->vehicleInspectionDefects()->where('status', TripPreTripInspectionDefect::STATUS_OPEN)->exists()) {
            $this->asset->update(['status' => 'operational']);
        }
    }
}
