<?php

namespace App\Filament\Maintenance\Widgets;

use App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource;
use App\Models\MaintenanceWorkOrder;
use App\Support\MaintenanceOptions;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OpenWorkOrdersWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Open priority work';

    protected function getTableQuery(): Builder
    {
        return MaintenanceWorkOrder::query()
            ->with(['asset', 'assignedTo'])
            ->open()
            ->orderByRaw("CASE priority WHEN 'emergency' THEN 1 WHEN 'urgent' THEN 2 WHEN 'high' THEN 3 WHEN 'normal' THEN 4 ELSE 5 END")
            ->orderBy('due_at');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('number')->searchable(),
            TextColumn::make('asset.display_name')->label('Asset')->wrap(),
            TextColumn::make('title')->wrap(),
            TextColumn::make('priority')->badge()->color(fn ($state) => MaintenanceOptions::colorForPriority($state)),
            TextColumn::make('status')->badge(),
            TextColumn::make('assignedTo.name')->label('Technician')->placeholder('Unassigned'),
            TextColumn::make('due_at')->dateTime('M j, Y')->placeholder('Not scheduled'),
        ];
    }

    protected function getTableActions(): array
    {
        return [Action::make('open')->url(fn (MaintenanceWorkOrder $record) => MaintenanceWorkOrderResource::getUrl('edit', ['record' => $record]))];
    }
}
