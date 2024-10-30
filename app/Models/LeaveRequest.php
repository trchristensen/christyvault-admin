<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Str;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'reason',
        'status',
        'reviewed_by',
        'review_notes',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    protected static function booted()
    {
        static::created(function ($leaveRequest) {
            $leaveRequest->event()->create([
                'uuid' => (string) str()->uuid(),
                'title' => "Time Off: {$leaveRequest->type} - {$leaveRequest->employee->name}",
                'start' => $leaveRequest->start_date,
                'end' => $leaveRequest->end_date,
                'type' => 'leave_request',  // Added type field
            ]);
        });

        static::updated(function ($leaveRequest) {
            if ($leaveRequest->event) {
                $leaveRequest->event->update([
                    'title' => "Time Off: {$leaveRequest->type} - {$leaveRequest->employee->name}",
                    'start' => $leaveRequest->start_date,
                    'end' => $leaveRequest->end_date,
                    'type' => 'leave_request',  // Added here too
                ]);
            }
        });

        static::deleted(function ($leaveRequest) {
            $leaveRequest->event?->delete();
        });
    }

    public function event()
    {
        return $this->morphOne(Event::class, 'eventable');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
