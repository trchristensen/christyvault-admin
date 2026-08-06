<?php

namespace App\Filament\Team\Resources\EmployeeProgramResource\Pages;

use App\Filament\Team\Resources\EmployeeProgramResource;
use App\Models\EmployeeProgram;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeProgram extends ViewRecord
{
    protected static string $resource = EmployeeProgramResource::class;

    protected string $view = 'filament.team.resources.employee-program-resource.pages.view-employee-program';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => auth()->user()?->canManagePrograms() ?? false),
            Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (EmployeeProgram $record): void {
                    $record->archive();
                    $this->redirect(EmployeeProgramResource::getUrl('index'));
                })
                ->visible(fn (EmployeeProgram $record): bool => (auth()->user()?->canManagePrograms() ?? false)
                    && $record->status !== EmployeeProgram::STATUS_ARCHIVED),
            Action::make('restore')
                ->icon('heroicon-o-arrow-uturn-left')
                ->action(fn (EmployeeProgram $record) => $record->restoreToLibrary())
                ->visible(fn (EmployeeProgram $record): bool => (auth()->user()?->canManagePrograms() ?? false)
                    && $record->status === EmployeeProgram::STATUS_ARCHIVED),
        ];
    }
}
