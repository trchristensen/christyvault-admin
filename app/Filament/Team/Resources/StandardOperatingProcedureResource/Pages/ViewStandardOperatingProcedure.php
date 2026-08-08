<?php

namespace App\Filament\Team\Resources\StandardOperatingProcedureResource\Pages;

use App\Filament\Team\Resources\StandardOperatingProcedureResource;
use App\Models\Employee;
use App\Models\StandardOperatingProcedure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;

class ViewStandardOperatingProcedure extends ViewRecord
{
    protected static string $resource = StandardOperatingProcedureResource::class;

    protected string $view = 'filament.team.resources.standard-operating-procedure-resource.pages.view-standard-operating-procedure';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('acknowledge')
                ->label('Acknowledge policy')
                ->icon('heroicon-o-pencil-square')
                ->color('success')
                ->modalHeading('Acknowledge this policy')
                ->modalDescription(fn (StandardOperatingProcedure $record): ?string => $record->currentRevision?->acknowledgement_text)
                ->modalSubmitActionLabel('Acknowledge policy')
                ->schema([
                    TextInput::make('signed_name')
                        ->label('Type your full name')
                        ->required()
                        ->maxLength(255),
                    Checkbox::make('confirmation')
                        ->label('I confirm that I personally reviewed this policy and intend this submission to be my electronic acknowledgment.')
                        ->accepted()
                        ->required(),
                ])
                ->fillForm(fn (): array => ['signed_name' => auth()->user()?->employee?->name ?? auth()->user()?->name])
                ->action(function (StandardOperatingProcedure $record, array $data): void {
                    $record->currentRevision?->acknowledge(
                        auth()->user()->employee,
                        auth()->user(),
                        $data['signed_name'],
                    );

                    Notification::make()
                        ->success()
                        ->title('Policy acknowledged')
                        ->body('Your acknowledgment is tied to this exact published version.')
                        ->send();
                })
                ->visible(function (StandardOperatingProcedure $record): bool {
                    $user = auth()->user();
                    $revision = $record->currentRevision;

                    return ! ($user?->canManageProcedures() ?? false)
                        && (bool) $user?->employee
                        && $revision?->document_type === StandardOperatingProcedure::TYPE_POLICY
                        && (bool) $revision?->acknowledgement_required
                        && ! $revision?->acknowledgementFor($user->employee);
                }),
            Action::make('record_paper_acknowledgement')
                ->label('Record paper acknowledgment')
                ->icon('heroicon-o-document-arrow-up')
                ->color('gray')
                ->modalHeading('Record a signed paper acknowledgment')
                ->modalDescription('This records who signed, when they signed, the exact policy revision, and who entered it. Uploading the scanned page is strongly recommended.')
                ->modalSubmitActionLabel('Record acknowledgment')
                ->schema([
                    Select::make('employee_id')
                        ->label('Employee')
                        ->options(fn (StandardOperatingProcedure $record): array => Employee::query()
                            ->where('is_active', true)
                            ->whereDoesntHave('documentAcknowledgements', fn ($query) => $query
                                ->where('standard_operating_procedure_revision_id', $record->current_revision_id))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('signed_name')
                        ->label('Name written on the form')
                        ->required()
                        ->maxLength(255),
                    DatePicker::make('acknowledged_at')
                        ->label('Date signed')
                        ->default(today())
                        ->maxDate(today())
                        ->native(false)
                        ->required(),
                    FileUpload::make('evidence_file_path')
                        ->label('Scanned signed page')
                        ->disk('local')
                        ->directory('policies/acknowledgements')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(20480),
                    Checkbox::make('recording_confirmation')
                        ->label('I confirm this accurately records a paper acknowledgment signed by the selected employee.')
                        ->accepted()
                        ->required()
                        ->columnSpanFull(),
                ])
                ->action(function (StandardOperatingProcedure $record, array $data): void {
                    $record->currentRevision?->recordPaperAcknowledgement(
                        Employee::query()->findOrFail($data['employee_id']),
                        auth()->user(),
                        $data['signed_name'],
                        Carbon::parse($data['acknowledged_at'])->endOfDay(),
                        $data['evidence_file_path'] ?? null,
                    );

                    Notification::make()->success()->title('Paper acknowledgment recorded')->send();
                })
                ->visible(fn (StandardOperatingProcedure $record): bool => (auth()->user()?->canManageProcedures() ?? false)
                    && $record->currentRevision?->document_type === StandardOperatingProcedure::TYPE_POLICY
                    && (bool) $record->currentRevision?->acknowledgement_required),
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
