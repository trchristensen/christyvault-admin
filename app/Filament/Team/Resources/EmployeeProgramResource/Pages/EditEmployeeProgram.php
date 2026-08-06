<?php

namespace App\Filament\Team\Resources\EmployeeProgramResource\Pages;

use App\Filament\Team\Resources\EmployeeProgramResource;
use App\Models\EmployeeProgram;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeProgram extends EditRecord
{
    protected static string $resource = EmployeeProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            Action::make('publish')
                ->label('Publish program')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Employees in the selected audience will be able to browse this program immediately.')
                ->action(function (EmployeeProgram $record): void {
                    $record->publish();

                    Notification::make()
                        ->success()
                        ->title('Program published')
                        ->body('Employees with access can now browse this program.')
                        ->send();

                    $this->redirect(EmployeeProgramResource::getUrl('view', ['record' => $record]));
                })
                ->visible(fn (EmployeeProgram $record): bool => $record->status === EmployeeProgram::STATUS_DRAFT),
            Action::make('move_to_draft')
                ->label('Unpublish')
                ->icon('heroicon-o-eye-slash')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Employees will no longer see this program. Its contents will remain available to managers.')
                ->action(fn (EmployeeProgram $record) => $record->moveToDraft())
                ->visible(fn (EmployeeProgram $record): bool => $record->status === EmployeeProgram::STATUS_PUBLISHED),
            Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (EmployeeProgram $record): void {
                    $record->archive();
                    $this->redirect(EmployeeProgramResource::getUrl('index'));
                })
                ->visible(fn (EmployeeProgram $record): bool => $record->status !== EmployeeProgram::STATUS_ARCHIVED),
            Action::make('restore')
                ->icon('heroicon-o-arrow-uturn-left')
                ->action(fn (EmployeeProgram $record) => $record->restoreToLibrary())
                ->visible(fn (EmployeeProgram $record): bool => $record->status === EmployeeProgram::STATUS_ARCHIVED),
        ];
    }
}
