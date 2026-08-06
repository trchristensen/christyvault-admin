<?php

namespace App\Filament\Team\Resources\StandardOperatingProcedureResource\Pages;

use App\Filament\Team\Resources\StandardOperatingProcedureResource;
use App\Models\StandardOperatingProcedure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStandardOperatingProcedure extends ViewRecord
{
    protected static string $resource = StandardOperatingProcedureResource::class;

    protected string $view = 'filament.team.resources.standard-operating-procedure-resource.pages.view-standard-operating-procedure';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => auth()->user()?->canManageProcedures() ?? false),
            Action::make('qr_sign')
                ->label('Print QR sign')
                ->icon('heroicon-o-qr-code')
                ->url(fn (StandardOperatingProcedure $record): string => route('procedures.public.label', $record->qr_token))
                ->openUrlInNewTab()
                ->visible(fn (StandardOperatingProcedure $record): bool => (auth()->user()?->canManageProcedures() ?? false)
                    && $record->public_qr_enabled
                    && $record->current_revision_id !== null
                    && ! $record->archived_at),
            Action::make('archive')
                ->label('Retire')
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (StandardOperatingProcedure $record): void {
                    $record->archive();
                    $this->redirect(StandardOperatingProcedureResource::getUrl('index'));
                })
                ->visible(fn (StandardOperatingProcedure $record): bool => (auth()->user()?->canManageProcedures() ?? false) && ! $record->archived_at),
            Action::make('restore')
                ->icon('heroicon-o-arrow-uturn-left')
                ->action(fn (StandardOperatingProcedure $record) => $record->restoreToLibrary())
                ->visible(fn (StandardOperatingProcedure $record): bool => (auth()->user()?->canManageProcedures() ?? false) && (bool) $record->archived_at),
        ];
    }
}
