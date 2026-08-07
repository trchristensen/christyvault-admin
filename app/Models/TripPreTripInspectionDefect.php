<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripPreTripInspectionDefect extends Model
{
    public const DRIVER_ASSESSMENT_REVIEW = 'review';

    public const DRIVER_ASSESSMENT_STOP = 'stop';

    public const OPERATING_DECISION_MAY_OPERATE = 'may_operate';

    public const OPERATING_DECISION_OUT_OF_SERVICE = 'out_of_service';

    public const STATUS_OPEN = 'open';

    public const STATUS_CORRECTED = 'corrected';

    public const STATUS_CORRECTION_NOT_REQUIRED = 'correction_not_required';

    protected $fillable = [
        'inspection_id',
        'maintenance_asset_id',
        'maintenance_request_id',
        'maintenance_work_order_id',
        'resolved_by_user_id',
        'reviewed_by_user_id',
        'component_key',
        'component_label',
        'description',
        'safety_related',
        'driver_assessment',
        'status',
        'operating_decision',
        'reported_at',
        'reviewed_at',
        'resolved_at',
        'resolution_notes',
        'review_notes',
        'resolution_certification',
    ];

    protected function casts(): array
    {
        return [
            'safety_related' => 'boolean',
            'reported_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(TripPreTripInspection::class, 'inspection_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAsset::class, 'maintenance_asset_id');
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceWorkOrder::class, 'maintenance_work_order_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
