<?php

namespace App\Filament\Maintenance\Widgets;

use App\Filament\Maintenance\Resources\MaintenanceAssetResource;
use App\Models\MaintenanceAsset;
use App\Models\MaintenanceWorkOrderPart;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AssetReliabilityWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Asset reliability · last 90 days';

    protected function getTableQuery(): Builder
    {
        return MaintenanceAsset::query()
            ->withCount([
                'workOrders as open_work_orders_count' => fn (Builder $query) => $query->open(),
                'workOrders as work_orders_90d_count' => fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(90)),
            ])
            ->withSum([
                'workOrders as downtime_minutes_90d' => fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(90)),
            ], 'downtime_minutes')
            ->whereHas('workOrders', fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(90)))
            ->orderByDesc('work_orders_90d_count');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('display_name')->label('Asset')->searchable(['asset_tag', 'name'])->wrap(),
            TextColumn::make('work_orders_90d_count')->label('Work orders')->numeric()->color(fn ($state) => $state >= 4 ? 'warning' : null),
            TextColumn::make('open_work_orders_count')->label('Still open')->numeric()->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
            TextColumn::make('downtime_hours')->label('Downtime')->state(fn (MaintenanceAsset $record) => round(((int) $record->downtime_minutes_90d) / 60, 1))->suffix(' hr'),
            TextColumn::make('parts_cost')->label('Recorded parts')->state(fn (MaintenanceAsset $record) => (float) MaintenanceWorkOrderPart::query()->whereHas('workOrder', fn (Builder $query) => $query->where('asset_id', $record->id)->where('created_at', '>=', now()->subDays(90)))->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as total')->value('total'))->money('USD'),
        ];
    }

    protected function getTableActions(): array
    {
        return [Action::make('open')->url(fn (MaintenanceAsset $record) => MaintenanceAssetResource::getUrl('edit', ['record' => $record]))];
    }
}
