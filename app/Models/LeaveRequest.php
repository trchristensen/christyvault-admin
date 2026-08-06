<?php

namespace App\Models;

use App\Notifications\LeaveRequestSubmitted;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

class LeaveRequest extends Model
{
    use HasFactory;

    public const DATE_RANGE_FORMAT = 'M j, Y';

    public const DATE_TIME_RANGE_FORMAT = 'M j, Y g:i A';

    public const DATE_RANGE_SEPARATOR = ' - ';

    protected $fillable = [
        'employee_id',
        'type',
        'date_range',
        'date_time_range',
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

    protected function dateRange(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->start_date && $this->end_date
                ? $this->start_date->format(self::DATE_RANGE_FORMAT)
                    .self::DATE_RANGE_SEPARATOR
                    .$this->end_date->format(self::DATE_RANGE_FORMAT)
                : null,
            set: function (?string $value): array {
                if (blank($value)) {
                    return [
                        'start_date' => null,
                        'end_date' => null,
                    ];
                }

                [$startDate, $endDate] = self::datesFromRange($value);

                return [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ];
            },
        );
    }

    protected function dateTimeRange(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->start_date && $this->end_date
                ? $this->start_date->format(self::DATE_TIME_RANGE_FORMAT)
                    .self::DATE_RANGE_SEPARATOR
                    .$this->end_date->format(self::DATE_TIME_RANGE_FORMAT)
                : null,
            set: function (?string $value): array {
                if (blank($value)) {
                    return [
                        'start_date' => null,
                        'end_date' => null,
                    ];
                }

                [$startDate, $endDate] = self::datesFromRange($value, includesTime: true);

                return [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ];
            },
        );
    }

    public static function datesFromRange(string $range, bool $includesTime = false): array
    {
        $parts = array_map('trim', explode(self::DATE_RANGE_SEPARATOR, $range, 2));

        if (count($parts) !== 2) {
            throw new InvalidArgumentException('The date range must contain a start and end date.');
        }

        $format = $includesTime ? self::DATE_TIME_RANGE_FORMAT : self::DATE_RANGE_FORMAT;
        $dates = array_map(function (string $date) use ($format): Carbon {
            $parsed = Carbon::createFromFormat('!'.$format, $date);

            if (! $parsed || $parsed->format($format) !== $date) {
                throw new InvalidArgumentException('The date range contains an invalid date.');
            }

            return $parsed;
        }, $parts);

        return [$dates[0], $dates[1]];
    }

    public function hasSpecificTimes(): bool
    {
        return $this->start_date && $this->end_date
            && (! $this->start_date->isStartOfDay() || ! $this->end_date->isStartOfDay());
    }

    public function dateSummary(): string
    {
        if ($this->hasSpecificTimes()) {
            return $this->start_date->isSameDay($this->end_date)
                ? $this->start_date->format('D, M j, Y · g:i A').' – '.$this->end_date->format('g:i A')
                : $this->start_date->format('D, M j · g:i A').' – '.$this->end_date->format('D, M j, Y · g:i A');
        }

        return $this->start_date->isSameDay($this->end_date)
            ? $this->start_date->format('D, M j, Y')
            : $this->start_date->format('M j').' – '.$this->end_date->format('M j, Y');
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->canViewAllTimeOffRequests()) {
            return $query;
        }

        $employee = $user->employee;

        return $query->where(function (Builder $visible) use ($employee, $user): void {
            $visible->whereRaw('1 = 0');

            if ($employee) {
                $visible->orWhere('employee_id', $employee->getKey());
            }

            if ($user->canViewPlantTimeOffRequests() && filled($employee?->christy_location)) {
                $visible->orWhereHas('employee', fn (Builder $employees): Builder => $employees
                    ->where('christy_location', $employee->christy_location));
            }
        });
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->canViewAllTimeOffRequests()) {
            return true;
        }

        if ((int) $this->employee_id === (int) $user->employee?->getKey()) {
            return true;
        }

        return $user->canViewPlantTimeOffRequests()
            && filled($user->employee?->christy_location)
            && $this->employee?->christy_location === $user->employee->christy_location;
    }

    public function isManageableBy(User $user): bool
    {
        if ($user->canManageAllTimeOffRequests()) {
            return true;
        }

        return $user->canManagePlantTimeOffRequests()
            && filled($user->employee?->christy_location)
            && $this->employee?->christy_location === $user->employee->christy_location;
    }

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

            if ($leaveRequest->status !== 'pending') {
                return;
            }

            $approvers = User::query()
                ->with('employee')
                ->get()
                ->filter(fn (User $user): bool => $leaveRequest->isManageableBy($user)
                    && ($user->canAccessPanelById('admin') || $user->canAccessPanelById('team')));

            if ($approvers->isNotEmpty()) {
                Notification::send($approvers, new LeaveRequestSubmitted($leaveRequest));
            }
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
