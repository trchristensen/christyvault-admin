<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Traits\HasTripForm;
use App\Filament\Resources\TripResource\Pages\CreateTrip;
use App\Filament\Resources\TripResource\Pages\EditTrip;
use App\Filament\Resources\TripResource\Pages\ListTrips;
use App\Filament\Resources\TripResource\RelationManagers\OrdersRelationManager;
use App\Models\Trip;
use App\Services\LoadPlanning\LoadDemandService;
use App\Services\LoadPlanning\RackDiagramService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('delivery_details')
                    ->label('Delivery Details')
                    ->html()
                    ->state(function (Trip $record): string {
                        $orders = $record->orders()->orderBy('stop_number')->get();
                        $ordersHtml = '';
                        $totalStops = $orders->count();

                        foreach ($orders as $order) {
                            $productsHtml = '';
                            foreach ($order->orderProducts as $orderProduct) {
                                $quantity = $orderProduct->fill_load ? 'Fill Load' : $orderProduct->quantity;
                                $productsHtml .= "• {$quantity} x {$orderProduct->product->sku}<br>";
                            }

                            $stopLabel = $totalStops > 1 ? "Stop {$order->stop_number} - " : '';

                            $ordersHtml .= "
                                <div class='p-2 mb-3 rounded bg-gray-50'>
                                    <div class='font-medium'>{$stopLabel}{$order->location->name}</div>
                                    <div class='text-sm text-gray-600'>{$order->location->full_address}</div>
                                    <div class='mt-1 text-sm text-gray-500'>{$productsHtml}</div>
                                </div>
                            ";
                        }

                        return "<div class='space-y-1'>{$ordersHtml}</div>";
                    })
                    ->alignLeft()
                    ->wrap(),
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
            ])
            ->recordActions([
                Action::make('loadSummary')
                    ->label('Load Summary')
                    ->icon('heroicon-o-cube-transparent')
                    ->color('info')
                    ->modalHeading(fn (Trip $record): string => "Load summary — {$record->trip_number}")
                    ->modalContent(function (Trip $record) {
                        $demand = app(LoadDemandService::class)->forTrip($record);

                        return view('filament.resources.trip-resource.load-summary', [
                            'result' => $demand->toArray(),
                            'diagram' => app(RackDiagramService::class)->forDemand($demand),
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('7xl'),
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
                    ->date()
                    ->collapsible(),
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
                'orders.location.preferredDeliveryContact',
                'orders.location',
                'orders.orderProducts.product',
                'driver',
                'vehicleConfiguration',
            ]);
    }
}
