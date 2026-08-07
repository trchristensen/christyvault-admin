<?php

namespace App\Filament\Maintenance\Resources;

use App\Filament\Maintenance\Resources\VehicleInspectionDefectResource\Pages\ListVehicleInspectionDefects;
use App\Models\MaintenanceWorkOrder;
use App\Models\TripPreTripInspectionDefect;
use App\Services\Maintenance\VehicleInspectionReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VehicleInspectionDefectResource extends Resource
{
    protected static ?string $model = TripPreTripInspectionDefect::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance Work';

    protected static ?string $navigationLabel = 'Inspection Issues';

    protected static ?string $modelLabel = 'inspection issue';

    protected static ?string $slug = 'inspection-defects';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reported_at')->label('Reported')->dateTime('M j, Y g:i A')->sortable(),
                TextColumn::make('asset.display_name')->label('Asset')->searchable(['asset_tag', 'name'])->wrap(),
                TextColumn::make('component_label')->label('Component')->searchable()->wrap(),
                TextColumn::make('description')->limit(80)->wrap(),
                TextColumn::make('inspection.driver_name')->label('Driver')->searchable(),
                TextColumn::make('driver_assessment')
                    ->label('Driver assessment')
                    ->formatStateUsing(fn ($state): string => $state === TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP ? 'Immediate safety concern' : 'Needs review')
                    ->badge()
                    ->color(fn ($state): string => $state === TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP ? 'danger' : 'warning'),
                TextColumn::make('status')->formatStateUsing(fn ($state): string => str($state)->replace('_', ' ')->headline()->toString())->badge()->color(fn ($state): string => $state === TripPreTripInspectionDefect::STATUS_OPEN ? 'danger' : 'success'),
                TextColumn::make('operating_decision')
                    ->label('Operating decision')
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        TripPreTripInspectionDefect::OPERATING_DECISION_MAY_OPERATE => 'May operate',
                        TripPreTripInspectionDefect::OPERATING_DECISION_OUT_OF_SERVICE => 'Out of service',
                        default => 'Awaiting review',
                    })
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        TripPreTripInspectionDefect::OPERATING_DECISION_MAY_OPERATE => 'success',
                        TripPreTripInspectionDefect::OPERATING_DECISION_OUT_OF_SERVICE => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('maintenanceRequest.id')->label('Request')->formatStateUsing(fn ($state): string => "Request #{$state}")->placeholder('—'),
                TextColumn::make('workOrder.number')->label('Work order')->placeholder('—'),
            ])
            ->defaultSort('reported_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    TripPreTripInspectionDefect::STATUS_OPEN => 'Open',
                    TripPreTripInspectionDefect::STATUS_CORRECTED => 'Corrected',
                    TripPreTripInspectionDefect::STATUS_CORRECTION_NOT_REQUIRED => 'Correction not required',
                ])->default(TripPreTripInspectionDefect::STATUS_OPEN),
            ])
            ->recordActions([
                Action::make('review_for_operation')
                    ->label('Review for operation')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('warning')
                    ->visible(fn (TripPreTripInspectionDefect $record): bool => $record->isOpen() && auth()->user()?->hasRole(['admin', 'super-admin', 'maintenance-manager']))
                    ->schema([
                        Select::make('operating_decision')->label('Carrier decision')->options([
                            TripPreTripInspectionDefect::OPERATING_DECISION_MAY_OPERATE => 'May operate — this issue does not require correction before dispatch',
                            TripPreTripInspectionDefect::OPERATING_DECISION_OUT_OF_SERVICE => 'Out of service — repair is required before operation',
                        ])->required(),
                        Textarea::make('review_notes')
                            ->label('Reason for decision')
                            ->helperText('Record what was checked and why operation is permitted or prohibited. This is the carrier’s decision, not the driver’s diagnosis.')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function (TripPreTripInspectionDefect $record, array $data): void {
                        $service = app(VehicleInspectionReportService::class);

                        if ($data['operating_decision'] === TripPreTripInspectionDefect::OPERATING_DECISION_MAY_OPERATE) {
                            $service->certifyResolution(
                                $record,
                                TripPreTripInspectionDefect::STATUS_CORRECTION_NOT_REQUIRED,
                                $data['review_notes'],
                                auth()->id(),
                            );
                        } else {
                            $service->requireRepairBeforeOperation(
                                $record,
                                $data['review_notes'],
                                auth()->id(),
                            );
                        }

                        Notification::make()->title('Operating decision recorded')->success()->send();
                    }),
                Action::make('certify_resolution')
                    ->label('Certify repair complete')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(fn (TripPreTripInspectionDefect $record): bool => $record->isOpen() && auth()->user()?->hasRole(['admin', 'super-admin', 'maintenance-manager']))
                    ->schema(fn (TripPreTripInspectionDefect $record): array => [
                        Select::make('maintenance_work_order_id')
                            ->label('Related work order')
                            ->options(MaintenanceWorkOrder::query()
                                ->where('asset_id', $record->maintenance_asset_id)
                                ->latest()
                                ->limit(100)
                                ->get()
                                ->mapWithKeys(fn (MaintenanceWorkOrder $workOrder): array => [$workOrder->getKey() => "{$workOrder->number} — {$workOrder->title}"])
                                ->all())
                            ->searchable(),
                        Textarea::make('resolution_notes')->label('What was repaired and checked?')->required()->rows(4),
                    ])
                    ->action(function (TripPreTripInspectionDefect $record, array $data): void {
                        if (filled($data['maintenance_work_order_id'] ?? null)) {
                            $record->update(['maintenance_work_order_id' => $data['maintenance_work_order_id']]);
                        }

                        app(VehicleInspectionReportService::class)->certifyResolution(
                            $record->refresh(),
                            TripPreTripInspectionDefect::STATUS_CORRECTED,
                            $data['resolution_notes'],
                            auth()->id(),
                        );

                        Notification::make()->title('Defect resolution certified')->success()->send();
                    }),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = TripPreTripInspectionDefect::query()->where('status', TripPreTripInspectionDefect::STATUS_OPEN)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getPages(): array
    {
        return ['index' => ListVehicleInspectionDefects::route('/')];
    }
}
