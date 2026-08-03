<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceFleetPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id', 'default_assignee_id', 'name', 'description', 'manufacturer',
        'asset_category', 'meter_type', 'meter_interval', 'service_provider',
        'service_contact_name', 'service_phone', 'priority', 'checklist', 'active',
        'last_generated_at', 'last_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'meter_interval' => 'decimal:2',
            'checklist' => 'array',
            'active' => 'boolean',
            'last_generated_at' => 'datetime',
            'last_completed_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function defaultAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assignee_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(MaintenanceFleetPlanAsset::class, 'fleet_plan_id');
    }

    public function serviceRuns(): HasMany
    {
        return $this->hasMany(MaintenanceFleetServiceRun::class, 'fleet_plan_id');
    }

    public function matchingAssetsQuery(): Builder
    {
        return MaintenanceAsset::query()
            ->where('location_id', $this->location_id)
            ->where('meter_type', $this->meter_type)
            ->where('status', '!=', 'retired')
            ->when(
                filled($this->manufacturer),
                fn (Builder $query): Builder => $query->whereRaw('LOWER(manufacturer) = ?', [mb_strtolower(trim($this->manufacturer))]),
            )
            ->when(
                filled($this->asset_category),
                fn (Builder $query): Builder => $query->where('category', $this->asset_category),
            );
    }

    public function serviceContactSummary(): string
    {
        return collect([
            $this->service_provider,
            $this->service_contact_name ? "Contact: {$this->service_contact_name}" : null,
            $this->service_phone ? "Phone: {$this->service_phone}" : null,
        ])->filter()->join("\n");
    }
}
