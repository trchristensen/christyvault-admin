<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\User;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

class LeaveCalendarFeedController extends Controller
{
    public function download($token)
    {
        // Validate the calendar token
        $user = User::where('calendar_token', $token)->first();
        
        if (!$user) {
            abort(404, 'Invalid calendar token');
        }

        $calendar = Calendar::create('Christy Vault Team Calendar')
            ->refreshInterval(5)
            ->timezone('America/Los_Angeles');

        LeaveRequest::query()
            ->with(['employee'])
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->get()
            ->each(function (LeaveRequest $leaveRequest) use ($calendar) {
                $calendar->event(
                    Event::create()
                        ->name("{$leaveRequest->employee->name} - {$leaveRequest->type}")
                        ->description($this->generateDescription($leaveRequest))
                        ->uniqueIdentifier($leaveRequest->id)
                        ->createdAt($leaveRequest->created_at)
                        ->startsAt($leaveRequest->start_date)
                        ->endsAt($leaveRequest->end_date)
                        ->fullDay()
                );
            });

        return response($calendar->get())
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="team-calendar.ics"');
    }

    private function generateDescription(LeaveRequest $leaveRequest): string
    {
        $description = [];

        $description[] = "Employee: {$leaveRequest->employee->name}";
        $description[] = "Type: {$leaveRequest->type}";
        $description[] = "Status: {$leaveRequest->status}";

        if ($leaveRequest->reason) {
            $description[] = "\nReason: {$leaveRequest->reason}";
        }

        if ($leaveRequest->review_notes) {
            $description[] = "\nNotes: {$leaveRequest->review_notes}";
        }

        return implode("\n", $description);
    }
}
