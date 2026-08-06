<?php

namespace App\Filament\Team\Resources\StandardOperatingProcedureResource\Pages;

use App\Filament\Team\Resources\StandardOperatingProcedureResource;
use App\Models\StandardOperatingProcedure;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStandardOperatingProcedure extends EditRecord
{
    protected static string $resource = StandardOperatingProcedureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            Action::make('publish')
                ->label(fn (StandardOperatingProcedure $record): string => $record->current_revision_id ? 'Publish new version' : 'Publish first version')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Publish this saved draft?')
                ->modalDescription('Employees will immediately see this as the current procedure. Save any unsaved form changes before publishing.')
                ->disabled(fn (StandardOperatingProcedure $record): bool => ! $record->hasUnpublishedChanges())
                ->visible(fn (StandardOperatingProcedure $record): bool => ! $record->archived_at)
                ->action(function (StandardOperatingProcedure $record): void {
                    $revision = $record->publishDraft(auth()->user());

                    Notification::make()
                        ->success()
                        ->title("Published {$revision->version_label}")
                        ->body('Employees with access can now read this procedure.')
                        ->send();

                    $this->redirect(StandardOperatingProcedureResource::getUrl('view', ['record' => $record]));
                }),
            Action::make('archive')
                ->label('Retire')
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Retire this procedure?')
                ->modalDescription('Employees and QR visitors will lose access, but every revision will be preserved.')
                ->action(function (StandardOperatingProcedure $record): void {
                    $record->archive();
                    $this->redirect(StandardOperatingProcedureResource::getUrl('index'));
                })
                ->visible(fn (StandardOperatingProcedure $record): bool => ! $record->archived_at),
            Action::make('restore')
                ->icon('heroicon-o-arrow-uturn-left')
                ->action(fn (StandardOperatingProcedure $record) => $record->restoreToLibrary())
                ->visible(fn (StandardOperatingProcedure $record): bool => (bool) $record->archived_at),
        ];
    }
}
