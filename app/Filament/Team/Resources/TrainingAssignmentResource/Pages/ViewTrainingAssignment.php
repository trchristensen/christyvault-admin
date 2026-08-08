<?php

namespace App\Filament\Team\Resources\TrainingAssignmentResource\Pages;

use App\Filament\Team\Resources\EmployeeProgramResource;
use App\Filament\Team\Resources\TrainingAssignmentResource;
use App\Models\TrainingAssignment;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTrainingAssignment extends ViewRecord
{
    protected static string $resource = TrainingAssignmentResource::class;

    protected string $view = 'filament.team.resources.training-assignment-resource.pages.view-training-assignment';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_program')
                ->label('Open training materials')
                ->icon('heroicon-o-rectangle-stack')
                ->url(fn (TrainingAssignment $record): string => EmployeeProgramResource::getUrl('view', ['record' => $record->program]))
                ->visible(fn (TrainingAssignment $record): bool => $record->program->isVisibleTo(auth()->user())),
            Action::make('start')
                ->label('Start training')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->action(function (TrainingAssignment $record): void {
                    $record->begin(auth()->user());
                    Notification::make()->success()->title('Training started')->send();
                })
                ->visible(fn (TrainingAssignment $record): bool => $record->belongsToUser(auth()->user())
                    && $record->status === TrainingAssignment::STATUS_ASSIGNED),
            Action::make('questionnaire')
                ->label('Take questionnaire')
                ->icon('heroicon-o-clipboard-document-check')
                ->modalHeading(fn (TrainingAssignment $record): string => $record->program->title.' questionnaire')
                ->modalDescription(fn (TrainingAssignment $record): string => 'A score of '.data_get($record->content_snapshot, 'passing_score', 80).'% is required. You can try again if needed.')
                ->modalSubmitActionLabel('Submit answers')
                ->modalWidth('3xl')
                ->schema(function (TrainingAssignment $record): array {
                    $fields = [];

                    foreach ($record->questionnaire() as $index => $question) {
                        $fields[] = Radio::make("answers.{$index}")
                            ->label(($index + 1).'. '.$question['prompt'])
                            ->options(collect($question['options'] ?? [])->pluck('label', 'key')->all())
                            ->required()
                            ->columnSpanFull();
                    }

                    $fields[] = Checkbox::make('certification')
                        ->label(TrainingAssignment::COMPLETION_CERTIFICATION)
                        ->accepted()
                        ->required()
                        ->columnSpanFull();

                    return $fields;
                })
                ->action(function (TrainingAssignment $record, array $data): void {
                    $attempt = $record->submitQuestionnaire(auth()->user(), $data['answers'] ?? []);

                    if (! $attempt->passed) {
                        Notification::make()
                            ->danger()
                            ->title("Score: {$attempt->score}%")
                            ->body('Review the materials and try the questionnaire again.')
                            ->send();

                        return;
                    }

                    $record->refresh();

                    if ($record->missingPolicyRevisionIds() !== []) {
                        Notification::make()
                            ->warning()
                            ->title("Passed with {$attempt->score}%")
                            ->body('Acknowledge the required policy before finishing this training.')
                            ->send();

                        return;
                    }

                    $record->complete(auth()->user());
                    Notification::make()->success()->title("Training completed · {$attempt->score}%")->send();
                })
                ->visible(fn (TrainingAssignment $record): bool => $record->belongsToUser(auth()->user())
                    && $record->status !== TrainingAssignment::STATUS_COMPLETED
                    && $record->questionnaire() !== []),
            Action::make('finish')
                ->label('Finish training')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(TrainingAssignment::COMPLETION_CERTIFICATION)
                ->action(function (TrainingAssignment $record): void {
                    $record->complete(auth()->user());
                    Notification::make()->success()->title('Training completed')->send();
                })
                ->visible(fn (TrainingAssignment $record): bool => $record->belongsToUser(auth()->user())
                    && $record->status !== TrainingAssignment::STATUS_COMPLETED
                    && $record->canComplete()),
        ];
    }
}
