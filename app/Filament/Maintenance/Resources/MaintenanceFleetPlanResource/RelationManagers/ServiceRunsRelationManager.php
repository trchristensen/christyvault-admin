<?php

namespace App\Filament\Maintenance\Resources\MaintenanceFleetPlanResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServiceRunsRelationManager extends RelationManager
{
    protected static string $relationship = 'serviceRuns';

    protected static ?string $title = 'Group service history';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('Service run'),
            TextColumn::make('triggeredByAsset.display_name')->label('Triggered by')->placeholder('Generated manually'),
            TextColumn::make('status')->badge(),
            TextColumn::make('work_orders_count')->counts('workOrders')->label('Work orders'),
            TextColumn::make('generated_at')->dateTime()->sortable(),
            TextColumn::make('completed_at')->dateTime()->placeholder('Open'),
        ])->defaultSort('generated_at', 'desc');
    }
}
