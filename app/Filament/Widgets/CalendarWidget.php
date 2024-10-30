<?php

namespace App\Filament\Widgets;

use App\Filament\Team\Resources\EventResource;
use App\Models\Event;
use App\Models\LeaveRequest;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class CalendarWidget extends FullCalendarWidget
{
    public function fetchEvents(array $fetchInfo): array
    {
        return Event::query()
            ->where('start', '>=', $fetchInfo['start'])
            ->where('end', '<=', $fetchInfo['end'])
            ->where(function ($query) {
                $query->where('eventable_type', '!=', LeaveRequest::class)
                    ->orWhereHas('eventable', function ($query) {
                        $query->whereIn('status', ['approved', 'pending']);
                    }, '>', 0);
            })
            ->get()
            ->map(function (Event $event) {
                $eventData = EventData::make()
                    ->id($event->id)          // Changed from uuid to id
                    ->title($event->title)
                    ->start($event->start)
                    ->end($event->end);

                // Different styling for leave requests
                if ($event->eventable_type === LeaveRequest::class) {
                    /** @var LeaveRequest $leaveRequest */
                    $leaveRequest = $event->eventable;

                    $color = match ($leaveRequest->type) {
                        'sick' => '#ff6b6b',     // Red
                        'vacation' => '#4dabf7',  // Blue
                        'unpaid' => '#fab005',    // Yellow
                        default => '#868e96'      // Gray
                    };

                    $eventData->backgroundColor($color);
                }

                return $eventData;
            })
            ->toArray();
    }
}
