<?php

namespace App\Filament\Maintenance\Resources;

use App\Filament\Maintenance\Resources\VehicleInspectionReportResource\Pages\ListVehicleInspectionReports;
use App\Models\TripPreTripInspection;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VehicleInspectionReportResource extends Resource
{
    protected static ?string $model = TripPreTripInspection::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance Work';

    protected static ?string $navigationLabel = 'Vehicle Inspections';

    protected static ?string $modelLabel = 'vehicle inspection report';

    protected static ?string $slug = 'vehicle-inspections';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('completed_at')->label('Submitted')->dateTime('M j, Y g:i A')->sortable(),
                TextColumn::make('report_type')->label('Report')->formatStateUsing(fn (TripPreTripInspection $record): string => $record->report_type_label)->badge(),
                TextColumn::make('driver_name')->label('Driver')->searchable(),
                TextColumn::make('assets.display_name')->label('Equipment')->bulleted()->wrap(),
                TextColumn::make('trip.trip_number')->label('Trip')->searchable(),
                IconColumn::make('safe_to_operate')->label('No defects')->boolean(),
                TextColumn::make('inspection_defects_count')->counts('inspectionDefects')->label('Defects')->badge(),
            ])
            ->defaultSort('completed_at', 'desc')
            ->filters([
                SelectFilter::make('report_type')->label('Report type')->options([
                    TripPreTripInspection::TYPE_PRE_TRIP => 'Pre-trip inspection',
                    TripPreTripInspection::TYPE_DAILY_REPORT => 'End-of-day vehicle report',
                ]),
                SelectFilter::make('safe_to_operate')->label('Result')->options([
                    1 => 'No defects reported',
                    0 => 'Defect reported',
                ]),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('View report')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (TripPreTripInspection $record): string => "{$record->report_type_label} — {$record->trip?->trip_number}")
                    ->modalContent(fn (TripPreTripInspection $record) => view('filament.maintenance.vehicle-inspection-report', [
                        'report' => $record->loadMissing(['assets', 'inspectionDefects.asset', 'inspectionDefects.resolvedBy', 'inspectionDefects.workOrder']),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('4xl'),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListVehicleInspectionReports::route('/')];
    }
}
