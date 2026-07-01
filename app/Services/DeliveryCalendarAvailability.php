<?php

namespace App\Services;

use App\Models\CalendarDay;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DeliveryCalendarAvailability
{
    public function isBlocked($date): bool
    {
        $date = Carbon::parse($date)->toDateString();
        $rules = $this->calendarDaysForDate($date);

        if ($rules->contains(fn(CalendarDay $day): bool => $day->blocks_delivery)) {
            return true;
        }

        if (Carbon::parse($date)->isWeekend()) {
            return ! $rules->contains(fn(CalendarDay $day): bool => $day->opens_delivery);
        }

        return false;
    }

    public function blockingReason($date): ?string
    {
        $date = Carbon::parse($date)->toDateString();
        $rules = $this->calendarDaysForDate($date);
        $blockingRules = $rules->filter(fn(CalendarDay $day): bool => $day->blocks_delivery);

        if ($blockingRules->isNotEmpty()) {
            return $blockingRules->pluck('name')->join(', ');
        }

        if (Carbon::parse($date)->isWeekend() && ! $rules->contains(fn(CalendarDay $day): bool => $day->opens_delivery)) {
            return 'Weekend';
        }

        return null;
    }

    public function validateDate($date, string $field = 'date'): void
    {
        if (! $date || ! $this->isBlocked($date)) {
            return;
        }

        $formattedDate = Carbon::parse($date)->format('M j, Y');
        $reason = $this->blockingReason($date) ?? 'Blocked day';

        throw ValidationException::withMessages([
            $field => "{$formattedDate} is blocked for delivery ({$reason}).",
        ]);
    }

    public function calendarDaysForDate($date): Collection
    {
        return CalendarDay::query()
            ->whereDate('date', Carbon::parse($date)->toDateString())
            ->orderByDesc('blocks_delivery')
            ->orderByDesc('opens_delivery')
            ->orderBy('name')
            ->get();
    }

    public function calendarDaysForRange($start, $end): Collection
    {
        return CalendarDay::query()
            ->whereDate('date', '>=', Carbon::parse($start)->toDateString())
            ->whereDate('date', '<=', Carbon::parse($end)->toDateString())
            ->orderBy('date')
            ->orderBy('name')
            ->get();
    }

    public function eventsForRange($start, $end): array
    {
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();
        $calendarDays = $this->calendarDaysForRange($startDate, $endDate);
        $calendarDaysByDate = $calendarDays->groupBy(fn(CalendarDay $day): string => $day->date->toDateString());
        $events = [];

        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            $dateString = $date->toDateString();
            $dayRules = $calendarDaysByDate->get($dateString, collect());
            $hasOpenOverride = $dayRules->contains(fn(CalendarDay $day): bool => $day->opens_delivery);
            $hasBlockingRule = $dayRules->contains(fn(CalendarDay $day): bool => $day->blocks_delivery);

            if ($date->isWeekend() && ! $hasOpenOverride && ! $hasBlockingRule) {
                $events[] = [
                    'id' => "weekend_block_{$dateString}",
                    'title' => 'Weekend blocked',
                    'start' => $dateString,
                    'allDay' => true,
                    'display' => 'background',
                    'backgroundColor' => 'rgba(148, 163, 184, 0.22)',
                    'classNames' => ['calendar-blocked-day', 'calendar-weekend-blocked'],
                    'editable' => false,
                    'extendedProps' => [
                        'type' => 'calendar_block',
                        'reason' => 'Weekend',
                        'blocks_delivery' => true,
                    ],
                ];
            }
        }

        foreach ($calendarDays as $calendarDay) {
            $dateString = $calendarDay->date->toDateString();

            if ($calendarDay->blocks_delivery) {
                $events[] = [
                    'id' => "calendar_day_block_{$calendarDay->id}",
                    'title' => $calendarDay->name,
                    'start' => $dateString,
                    'allDay' => true,
                    'display' => 'background',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.16)',
                    'classNames' => ['calendar-blocked-day', 'calendar-rule-blocked'],
                    'editable' => false,
                    'extendedProps' => [
                        'type' => 'calendar_block',
                        'calendar_day_id' => $calendarDay->id,
                        'reason' => $calendarDay->name,
                        'blocks_delivery' => true,
                    ],
                ];
            }

            $classNames = [
                'calendar-day-event',
                "calendar-day-{$calendarDay->type}",
                $calendarDay->blocks_delivery ? 'calendar-day-blocking' : 'calendar-day-informational',
            ];

            if ($calendarDay->opens_delivery) {
                $classNames[] = 'calendar-day-open';
            }

            $events[] = [
                'id' => "calendar_day_{$calendarDay->id}",
                'title' => $calendarDay->name,
                'start' => $dateString,
                'allDay' => true,
                'editable' => false,
                'classNames' => $classNames,
                'backgroundColor' => $this->eventColor($calendarDay),
                'borderColor' => $this->eventColor($calendarDay),
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'type' => 'calendar_day',
                    'calendar_day_id' => $calendarDay->id,
                    'calendar_day_type' => $calendarDay->type,
                    'type_label' => $calendarDay->type_label,
                    'blocks_delivery' => $calendarDay->blocks_delivery,
                    'opens_delivery' => $calendarDay->opens_delivery,
                    'notes' => $calendarDay->notes,
                ],
            ];
        }

        return $events;
    }

    private function eventColor(CalendarDay $calendarDay): string
    {
        if ($calendarDay->opens_delivery) {
            return '#16a34a';
        }

        if ($calendarDay->blocks_delivery) {
            return '#dc2626';
        }

        return '#2563eb';
    }
}
