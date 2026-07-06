<?php

namespace App\Filament\Resources\LocationResource\RelationManagers;

use App\Enums\PlantLocation;
use App\Filament\Resources\LocationResource;
use App\Models\Location;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

class NearbyLocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Nearby Locations';

    protected function getTableQuery(): Builder | Relation | null
    {
        /** @var Location $owner */
        $owner = $this->getOwnerRecord();

        return $this->nearbyLocationsQuery($owner);
    }

    protected function nearbyLocationsQuery(Location $owner): Builder
    {
        if (! $owner->hasCoordinates()) {
            return Location::query()->whereRaw('1 = 0');
        }

        $latitude = (float) $owner->latitude;
        $longitude = (float) $owner->longitude;

        return Location::query()
            ->select('locations.*')
            ->selectRaw(
                '3958.7613 * acos(least(1, greatest(-1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))) as distance_miles',
                [$latitude, $longitude, $latitude]
            )
            ->whereKeyNot($owner->getKey())
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');
    }

    protected function nearbyLocationsForMap(): Collection
    {
        /** @var Location $owner */
        $owner = $this->getOwnerRecord();

        return $this->nearbyLocationsQuery($owner)
            ->orderBy('distance_miles')
            ->limit(50)
            ->get();
    }

    public function table(Table $table): Table
    {
        return $table
            ->header(fn() => view('filament.resources.location-resource.relation-managers.nearby-locations-map', [
                'owner' => $this->getOwnerRecord(),
                'locations' => $this->nearbyLocationsForMap(),
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('state')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('default_plant_location')
                    ->label('Default Delivery Type')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof PlantLocation) {
                            return $state->getLabel();
                        }

                        return PlantLocation::tryFrom((string) $state)?->getLabel() ?? 'Colma';
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('distance_miles')
                    ->label('Miles Away')
                    ->formatStateUsing(fn($state): string => number_format((float) $state, 1) . ' mi')
                    ->sortable(query: fn(Builder $query, string $direction): Builder => $query->orderBy('distance_miles', $direction)),
                Tables\Columns\TextColumn::make('full_address')
                    ->label('Address')
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_order_at')
                    ->label('Last Ordered')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort(fn(Builder $query): Builder => $query->orderBy('distance_miles'))
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No nearby locations')
            ->emptyStateDescription('Add coordinates to this location and other locations to calculate nearby delivery stops.')
            ->actions([
                Tables\Actions\Action::make('open_location')
                    ->label('Open Location')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn(Location $record): string => LocationResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
