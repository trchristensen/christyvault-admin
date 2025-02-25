<?php

namespace App\Models;

use App\Enums\SalesVisitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesVisit extends Model
{
    protected $fillable = [
        'location_id',
        'employee_id',
        'contact_id',
        'planned_at',
        'completed_at',
        'status',
        'visit_notes',
        'followup_summary'
    ];

    protected $casts = [
        'status' => SalesVisitStatus::class,
        'planned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    // Helper method to get customer through location if needed
    public function customer()
    {
        return $this->location->customer;
    }
}
