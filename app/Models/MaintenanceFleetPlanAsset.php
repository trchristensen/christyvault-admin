<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceFleetPlanAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'fleet_plan_id', 'asset_id', 'included', 'matches_filter', 'baseline_meter',
        'next_due_meter', 'last_serviced_at',
    ];

    protected function casts(): array
    {
        return [
            'included' => 'boolean',
            'matches_filter' => 'boolean',
            'baseline_meter' => 'decimal:2',
            'next_due_meter' => 'decimal:2',
            'last_serviced_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenanceFleetPlan::class, 'fleet_plan_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAsset::class, 'asset_id');
    }
}
