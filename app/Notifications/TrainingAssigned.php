<?php

namespace App\Notifications;

use App\Filament\Team\Resources\TrainingAssignmentResource;
use App\Models\TrainingAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TrainingAssigned extends Notification
{
    use Queueable;

    public function __construct(public TrainingAssignment $assignment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'panel' => 'team',
            'title' => 'New training assigned',
            'message' => $this->assignment->program->title,
            'training_assignment_id' => $this->assignment->getKey(),
            'url' => TrainingAssignmentResource::getUrl('view', [
                'record' => $this->assignment,
            ], panel: 'team'),
        ];
    }
}
