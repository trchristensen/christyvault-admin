<?php

namespace App\Filament\Maintenance\Resources\MaintenanceAssetResource\Pages;

use App\Filament\Maintenance\Resources\MaintenanceAssetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMaintenanceAssets extends ListRecords
{
    protected static string $resource = MaintenanceAssetResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'colma' => Tab::make('Colma')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->forPlant($query, 'Colma')),
            'tulare' => Tab::make('Tulare')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->forPlant($query, 'Tulare')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    private function forPlant(Builder $query, string $city): Builder
    {
        return $query->whereHas(
            'location',
            fn (Builder $locationQuery): Builder => $locationQuery
                ->christyVault()
                ->where('city', $city),
        );
    }
}
