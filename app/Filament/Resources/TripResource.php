<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TripResource\Pages;
use App\Filament\Resources\TripResource\RelationManagers;
use App\Models\Driver;
use App\Models\Trip;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Order;
use App\Models\Employee;

class TripResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Operations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Trip Information')
                    ->schema([
                        Forms\Components\TextInput::make('trip_number')
                            ->required()
                            ->default(function () {
                                $latestTrip = Trip::latest()->first();
                                $lastNumber = $latestTrip ? intval(substr($latestTrip->trip_number, -5)) : 0;
                                $nextNumber = $lastNumber + 1;
                                return 'TRIP-' . date('Y') . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
                            })
                            ->readOnly(),

                        Forms\Components\Select::make('driver_id')
                            ->relationship('driver', 'name')
                            ->placeholder('Select Driver')
                            ->required()
                            ->options(Employee::where('position', 'driver')->pluck('name', 'id'))
                            ->preload(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required(),
                        Forms\Components\DatePicker::make('scheduled_date')
                            ->required(),
                        Forms\Components\TimePicker::make('start_time'),
                        // ->required(),
                        Forms\Components\TimePicker::make('end_time'),
                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('trip_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('driver.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('orders')
                    ->label('Customers')
                    ->formatStateUsing(function ($record) {
                        $orders = $record->orders()->orderBy('stop_number')->get();

                        // If only one order, show without stop number
                        if ($orders->count() === 1) {
                            $order = $orders->first();
                            return ($order->customer?->name ?? 'No Customer') .
                                ' (' . ($order->location?->city ?? 'No Location') . ')';
                        }

                        // Multiple orders, show with stop numbers
                        return $orders
                            ->map(function ($order) {
                                $customerName = $order->customer?->name ?? 'No Customer';
                                $cityName = $order->location?->city ?? 'No Location';
                                return "Stop {$order->stop_number} - {$customerName} ({$cityName})";
                            })
                            ->implode('<br>');
                    })
                    ->html(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'gray',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('scheduled_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->time(),
                Tables\Columns\TextColumn::make('end_time')
                    ->time(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultGroup('scheduled_date')
            ->groups([
                Tables\Grouping\Group::make('scheduled_date')
                    ->label('Delivery Date')
                    ->date()
                    ->collapsible()
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrips::route('/'),
            'create' => Pages\CreateTrip::route('/create'),
            'edit' => Pages\EditTrip::route('/{record}/edit'),
        ];
    }
}
