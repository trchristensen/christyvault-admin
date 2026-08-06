<?php

namespace App\Rules;

use App\Models\LeaveRequest;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

class WeekdayDateRange implements ValidationRule
{
    public function __construct(
        protected bool $includesTime = false,
        protected bool $allowPast = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || blank($value)) {
            return;
        }

        try {
            [$startDate, $endDate] = LeaveRequest::datesFromRange($value, $this->includesTime);
        } catch (Throwable) {
            $fail('Select a valid start and end date.');

            return;
        }

        if (! $this->allowPast && $startDate->isBefore(today()->startOfDay())) {
            $fail('The first day off cannot be in the past.');
        }

        if ($endDate->isBefore($startDate)) {
            $fail('The last day off must be on or after the first day.');
        }

        if ($this->includesTime && $endDate->lte($startDate)) {
            $fail('The ending date and time must be after the starting date and time.');
        }

        if ($startDate->isWeekend() || $endDate->isWeekend()) {
            $fail('Time-off requests must start and end on a weekday.');
        }
    }
}
