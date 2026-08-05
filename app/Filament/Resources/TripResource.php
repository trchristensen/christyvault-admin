<?php

namespace App\Filament\Resources;

use App\Enums\PlantLocation;
use App\Enums\TripStatus;
use App\Filament\Actions\TripLoadSummaryAction;
use App\Filament\Resources\Traits\HasTripForm;
use App\Filament\Resources\TripResource\Pages\CreateTrip;
use App\Filament\Resources\TripResource\Pages\EditTrip;
use App\Filament\Resources\TripResource\Pages\ListTrips;
use App\Filament\Resources\TripResource\RelationManagers\OrdersRelationManager;
use App\Models\Trip;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TripResource extends Resource
{
    use HasTripForm;

    protected static ?string $model = Trip::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'Delivery Management';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(static::getTripFormSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('delivery_details')
                    ->label('Delivery Details')
                    ->state(function (Trip $record): array {
                        $orders = $record->orderedDeliveryOrders();
                        $showStopNumbers = $orders->count() > 1;

                        return $orders
                            ->map(function ($order, int $index) use ($showStopNumbers): string {
                                $destination = $order->location?->name ?? 'Location not set';

                                if (filled($order->location?->city)) {
                                    $destination .= " — {$order->location->city}";
                                }

                                return $showStopNumbers
                                    ? 'Stop '.($index + 1).": {$destination}"
                                    : $destination;
                            })
                            ->all();
                    })
                    ->listWithLineBreaks()
                    ->placeholder('No delivery stops assigned')
                    ->alignLeft()
                    ->wrap(),
                TextColumn::make('plant_locations')
                    ->label('Plant')
                    ->state(fn (Trip $record): array => $record->orderedDeliveryOrders()
                        ->pluck('plant_location')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all())
                    ->formatStateUsing(fn (string $state): string => match (PlantLocation::tryFrom($state)) {
                        PlantLocation::TULARE_PLANT => 'Tulare',
                        PlantLocation::COLMA_MAIN => 'Colma',
                        PlantLocation::COLMA_LOCALS => 'Colma Locals',
                        default => str($state)->headline(),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        PlantLocation::TULARE_PLANT->value => 'info',
                        PlantLocation::COLMA_MAIN->value => 'success',
                        PlantLocation::COLMA_LOCALS->value => 'warning',
                        default => 'gray',
                    })
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'in_progress' => 'warning',
                        // need a color for confirmed
                        'confirmed' => 'purple',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    }),
                TextColumn::make('scheduled_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('trip_number')
                    ->searchable(),
                TextColumn::make('driver.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('vehicleConfiguration.name')
                    ->label('Vehicle')
                    ->placeholder('Not selected')
                    ->badge()
                    ->sortable(),
            ])
            ->recordActions([
                TripLoadSummaryAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('updateStatus')
                        ->label('Update Status')
                        ->icon('heroicon-o-truck')
                        ->action(function (Collection $records, array $data): void {
                            $records->each(function ($record) use ($data) {
                                $record->update(['status' => $data['status']]);
                            });
                        })
                        ->form([
                            Select::make('status')
                                ->options([
                                    'pending' => 'Pending',
                                    'in_progress' => 'In Progress',
                                    'completed' => 'Completed',
                                    'cancelled' => 'Cancelled',
                                ])
                                ->required(),
                        ]),
                ]),
            ])
            ->defaultGroup('scheduled_date')
            ->groups([
                Group::make('scheduled_date')
                    ->label('Delivery Date')
                    ->getKeyFromRecordUsing(fn (Trip $record): string => $record->scheduled_date?->toDateString() ?? 'unscheduled')
                    ->getTitleFromRecordUsing(fn (Trip $record): string => $record->scheduled_date?->format('l, F j, Y') ?? 'Unscheduled')
                    ->scopeQueryByKeyUsing(fn (Builder $query, string $key): Builder => $key === 'unscheduled'
                        ? $query->whereNull('scheduled_date')
                        : $query->whereDate('scheduled_date', $key))
                    ->collapsible(),
                Group::make('route_plant')
                    ->label('Plant')
                    ->getKeyFromRecordUsing(fn (Trip $record): string => (string) (
                        $record->orderedDeliveryOrders()->first()?->plant_location ?? 'not_set'
                    ))
                    ->getTitleFromRecordUsing(fn (Trip $record): string => static::plantLabel(
                        $record->orderedDeliveryOrders()->first()?->plant_location,
                    ))
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw(static::firstStopPlantSql().' '.$direction))
                    ->scopeQueryByKeyUsing(function (Builder $query, string $key): Builder {
                        $expression = static::firstStopPlantSql();

                        return $key === 'not_set'
                            ? $query->whereRaw("{$expression} IS NULL")
                            : $query->whereRaw("{$expression} = ?", [$key]);
                    })
                    ->collapsible(),
            ])
            ->filters([
                SelectFilter::make('plant')
                    ->label('Plant')
                    ->options([
                        PlantLocation::TULARE_PLANT->value => 'Tulare',
                        PlantLocation::COLMA_MAIN->value => 'Colma',
                        PlantLocation::COLMA_LOCALS->value => 'Colma Locals',
                    ])
                    ->multiple()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['values'] ?? [],
                        fn (Builder $query, array $plants): Builder => $query->whereHas(
                            'orders',
                            fn (Builder $query): Builder => $query->whereIn('plant_location', $plants),
                        ),
                    )),
                SelectFilter::make('driver_id')
                    ->label('Driver')
                    ->relationship('driver', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options(collect(TripStatus::cases())
                        ->mapWithKeys(fn (TripStatus $status): array => [$status->value => $status->label()])
                        ->all())
                    ->multiple(),
                Filter::make('scheduled_date_range')
                    ->label('Scheduled Date')
                    ->schema([
                        DatePicker::make('from')
                            ->native(false),
                        DatePicker::make('until')
                            ->native(false),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate('scheduled_date', '>=', $date),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate('scheduled_date', '<=', $date),
                        )),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrips::route('/'),
            'create' => CreateTrip::route('/create'),
            'edit' => EditTrip::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'orders.location',
                'stops.order.location',
                'driver',
                'vehicleConfiguration',
            ]);
    }

    protected static function plantLabel(mixed $plant): string
    {
        return match (PlantLocation::tryFrom((string) $plant)) {
            PlantLocation::TULARE_PLANT => 'Tulare',
            PlantLocation::COLMA_MAIN => 'Colma',
            PlantLocation::COLMA_LOCALS => 'Colma Locals',
            default => 'Plant not set',
        };
    }

    protected static function firstStopPlantSql(): string
    {
        return "(
            SELECT orders.plant_location
            FROM orders
            WHERE orders.trip_id = trips.id
                AND orders.deleted_at IS NULL
            ORDER BY
                CASE WHEN orders.stop_number IS NULL THEN 1 ELSE 0 END,
                orders.stop_number,
                orders.id
            LIMIT 1
        )";
    }
}
