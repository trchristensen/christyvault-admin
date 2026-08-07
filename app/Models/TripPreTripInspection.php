<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripPreTripInspection extends Model
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DEFECT_REPORTED = 'defect_reported';

    public const TYPE_PRE_TRIP = 'pre_trip';

    public const TYPE_DAILY_REPORT = 'daily_report';

    protected $fillable = [
        'trip_id',
        'user_id',
        'driver_id',
        'vehicle_configuration_id',
        'truck_asset_id',
        'trailer_asset_id',
        'piggyback_asset_id',
        'inspection_date',
        'scheduled_date',
        'checklist_version',
        'report_type',
        'prior_report_reviewed_at',
        'status',
        'safe_to_operate',
        'driver_name',
        'vehicle_configuration_snapshot',
        'equipment_snapshot',
        'responses',
        'defects',
        'defect_notes',
        'certification_text',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'inspection_date' => 'date',
            'scheduled_date' => 'date',
            'safe_to_operate' => 'boolean',
            'prior_report_reviewed_at' => 'datetime',
            'vehicle_configuration_snapshot' => 'array',
            'equipment_snapshot' => 'array',
            'responses' => 'array',
            'defects' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    public function vehicleConfiguration(): BelongsTo
    {
        return $this->belongsTo(VehicleConfiguration::class);
    }

    public function truckAsset(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAsset::class, 'truck_asset_id');
    }

    public function trailerAsset(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAsset::class, 'trailer_asset_id');
    }

    public function piggybackAsset(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAsset::class, 'piggyback_asset_id');
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(
            MaintenanceAsset::class,
            'trip_pre_trip_inspection_assets',
            'inspection_id',
            'maintenance_asset_id',
        )->withPivot(['role', 'asset_snapshot'])->withTimestamps();
    }

    public function inspectionDefects(): HasMany
    {
        return $this->hasMany(TripPreTripInspectionDefect::class, 'inspection_id');
    }

    public function getReportTypeLabelAttribute(): string
    {
        return $this->report_type === self::TYPE_DAILY_REPORT
            ? 'End-of-day vehicle report'
            : 'Pre-trip inspection';
    }

    public function hasOpenIssues(): bool
    {
        $defects = $this->relationLoaded('inspectionDefects')
            ? $this->inspectionDefects
            : $this->inspectionDefects()->get();

        return $defects->contains(fn (TripPreTripInspectionDefect $defect): bool => $defect->isOpen());
    }

    public function requiresImmediateStop(): bool
    {
        $defects = $this->relationLoaded('inspectionDefects')
            ? $this->inspectionDefects
            : $this->inspectionDefects()->get();

        return $defects->contains(fn (TripPreTripInspectionDefect $defect): bool => $defect->isOpen() && (
            $defect->driver_assessment === TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP
            || $defect->operating_decision === TripPreTripInspectionDefect::OPERATING_DECISION_OUT_OF_SERVICE
        ));
    }
}
