<?php

namespace App\Filament\Maintenance\Resources\MaintenanceAssetResource\RelationManagers;

use App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource;
use App\Models\MaintenanceWorkOrder;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'workOrders';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number')->searchable(),
            TextColumn::make('title')->wrap(),
            TextColumn::make('priority')->badge(),
            TextColumn::make('status')->badge(),
            TextColumn::make('assignedTo.name')->label('Technician'),
            TextColumn::make('due_at')->dateTime(),
        ])->defaultSort('created_at', 'desc')->recordActions([
            Action::make('open')->icon('heroicon-o-arrow-top-right-on-square')->url(fn (MaintenanceWorkOrder $record) => MaintenanceWorkOrderResource::getUrl('edit', ['record' => $record])),
        ]);
    }
}
