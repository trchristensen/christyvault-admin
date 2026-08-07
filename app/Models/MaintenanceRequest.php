<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MaintenanceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id', 'location_id', 'requested_by_user_id', 'triaged_by_user_id', 'requester_name',
        'requester_contact', 'title', 'description', 'priority', 'safety_related', 'status',
        'photo_paths', 'triage_notes', 'submitted_at', 'triaged_at',
    ];

    protected function casts(): array
    {
        return [
            'safety_related' => 'boolean',
            'photo_paths' => 'array',
            'submitted_at' => 'datetime',
            'triaged_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAsset::class, 'asset_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function triagedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triaged_by_user_id');
    }

    public function workOrder(): HasOne
    {
        return $this->hasOne(MaintenanceWorkOrder::class, 'request_id');
    }

    public function vehicleInspectionDefects()
    {
        return $this->hasMany(TripPreTripInspectionDefect::class, 'maintenance_request_id');
    }
}
