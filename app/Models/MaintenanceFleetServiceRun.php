<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceFleetServiceRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'fleet_plan_id', 'triggered_by_asset_id', 'status', 'generated_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenanceFleetPlan::class, 'fleet_plan_id');
    }

    public function triggeredByAsset(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAsset::class, 'triggered_by_asset_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class, 'fleet_service_run_id');
    }
}
