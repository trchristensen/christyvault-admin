<?php

namespace App\Filament\Team\Resources\TrainingAssignmentResource\Pages;

use App\Filament\Team\Resources\TrainingAssignmentResource;
use App\Models\Employee;
use App\Models\EmployeeProgram;
use App\Models\TrainingAssignment;
use App\Notifications\TrainingAssigned;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;

class ListTrainingAssignments extends ListRecords
{
    protected static string $resource = TrainingAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assign_training')
                ->label('Assign training')
                ->icon('heroicon-o-user-plus')
                ->visible(fn (): bool => auth()->user()?->canManageTraining() ?? false)
                ->modalHeading('Assign employee training')
                ->modalDescription('Choose a published program that has training enabled, then select the employees who are required to complete it. Existing open assignments are not duplicated.')
                ->schema([
                    Select::make('employee_program_id')
                        ->label('Training program')
                        ->helperText('Only published programs with “Make this program assignable as training” enabled appear here.')
                        ->options(fn (): array => EmployeeProgram::query()
                            ->where('status', EmployeeProgram::STATUS_PUBLISHED)
                            ->where('training_enabled', true)
                            ->orderBy('title')
                            ->pluck('title', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),
                    Select::make('employee_ids')
                        ->label('Employees')
                        ->multiple()
                        ->options(function (Get $get): array {
                            $program = EmployeeProgram::query()->find($get('employee_program_id'));

                            if (! $program) {
                                return [];
                            }

                            return Employee::query()
                                ->where('is_active', true)
                                ->whereNotNull('user_id')
                                ->with(['positions', 'user.roles'])
                                ->orderBy('name')
                                ->get()
                                ->filter(fn (Employee $employee): bool => $program->appliesToEmployee($employee))
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->required(),
                    DatePicker::make('due_date')
                        ->label('Due date')
                        ->minDate(today())
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    $program = EmployeeProgram::query()
                        ->where('status', EmployeeProgram::STATUS_PUBLISHED)
                        ->where('training_enabled', true)
                        ->findOrFail($data['employee_program_id']);
                    $created = 0;

                    foreach (Employee::query()->whereKey($data['employee_ids'])->with(['positions', 'user.roles'])->get() as $employee) {
                        if (! $employee->user || ! $program->appliesToEmployee($employee)) {
                            continue;
                        }

                        $hasOpenAssignment = TrainingAssignment::query()
                            ->where('employee_program_id', $program->getKey())
                            ->where('employee_id', $employee->getKey())
                            ->whereIn('status', [TrainingAssignment::STATUS_ASSIGNED, TrainingAssignment::STATUS_IN_PROGRESS])
                            ->exists();

                        if ($hasOpenAssignment) {
                            continue;
                        }

                        $assignment = TrainingAssignment::query()->create([
                            'employee_program_id' => $program->getKey(),
                            'employee_id' => $employee->getKey(),
                            'assigned_by_user_id' => auth()->id(),
                            'due_date' => $data['due_date'] ?? null,
                            'locale' => $employee->preferred_locale ?: $program->default_locale ?: 'en',
                        ]);

                        $employee->user->notify(new TrainingAssigned($assignment));
                        $created++;
                    }

                    Notification::make()
                        ->title($created > 0 ? 'Training assigned' : 'No new assignments created')
                        ->body($created > 0
                            ? "Created {$created} ".str('assignment')->plural($created).'.'
                            : 'Every selected employee already had an open assignment or was outside the program audience.')
                        ->status($created > 0 ? 'success' : 'warning')
                        ->send();
                }),
        ];
    }
}
