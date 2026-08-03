<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenancePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id', 'default_assignee_id', 'name', 'description', 'trigger_type', 'interval_value',
        'interval_unit', 'meter_interval', 'next_due_date', 'next_due_meter', 'lead_days',
        'priority', 'checklist', 'active', 'last_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'meter_interval' => 'decimal:2',
            'next_due_date' => 'date',
            'next_due_meter' => 'decimal:2',
            'checklist' => 'array',
            'active' => 'boolean',
            'last_generated_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAsset::class, 'asset_id');
    }

    public function defaultAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assignee_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class, 'plan_id');
    }
}
