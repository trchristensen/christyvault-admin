<?php

namespace App\Filament\Maintenance\Resources\MaintenanceAssetResource\RelationManagers;

use App\Models\TripPreTripInspection;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VehicleInspectionReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'vehicleInspectionReports';

    protected static ?string $title = 'Vehicle inspection history';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('completed_at')->label('Submitted')->dateTime('M j, Y g:i A')->sortable(),
                TextColumn::make('report_type')->label('Report')->formatStateUsing(fn (TripPreTripInspection $record): string => $record->report_type_label)->badge(),
                TextColumn::make('driver_name')->label('Driver'),
                TextColumn::make('trip.trip_number')->label('Trip'),
                IconColumn::make('safe_to_operate')->label('No defects')->boolean(),
            ])
            ->defaultSort('completed_at', 'desc')
            ->recordActions([
                Action::make('details')->label('View')->icon('heroicon-o-eye')
                    ->modalHeading(fn (TripPreTripInspection $record): string => $record->report_type_label)
                    ->modalContent(fn (TripPreTripInspection $record) => view('filament.maintenance.vehicle-inspection-report', [
                        'report' => $record->loadMissing(['assets', 'inspectionDefects.asset', 'inspectionDefects.resolvedBy', 'inspectionDefects.workOrder']),
                    ]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Close')->modalWidth('4xl'),
            ]);
    }
}
