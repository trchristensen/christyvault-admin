<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Product;
use Filament\Tables\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Order Details')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->options(Customer::pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Forms\Components\DatePicker::make('requested_delivery_date')
                            ->required()
                            ->minDate(now()),
                        Forms\Components\DatePicker::make('actual_delivery_date')
                            // ->required()
                            ->minDate(now()),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'in_production' => 'In Production',
                                'ready_for_delivery' => 'Ready for Delivery',
                                'out_for_delivery' => 'Out for Delivery',
                                'delivered' => 'Delivered',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required(),
                        Forms\Components\Textarea::make('special_instructions')
                            ->maxLength(1000),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Products')
                    ->schema([
                        Forms\Components\Repeater::make('orderProducts')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->options(Product::query()->pluck('name', 'id'))
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(
                                        fn($state, callable $set) =>
                                        $set('price', Product::find($state)?->price ?? 0)
                                    ),
                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required(),
                                Forms\Components\TextInput::make('price')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required(),
                                Forms\Components\TextInput::make('notes')
                                    ->nullable()
                                    ->columnSpanFull()
                            ])
                            ->columns(3)
                    ]),
                Forms\Components\Select::make('driver_id')
                    ->label('Driver')
                    ->options(Employee::where('position', 'driver')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('requested_delivery_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('actual_delivery_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->sortable()
                    ->color(fn(string $state): string => match ($state) {
                        'cancelled' => 'danger',
                        'pending' => 'warning',
                        'delivered' => 'success',
                        default => 'primary',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'in_production' => 'In Production',
                        'ready_for_delivery' => 'Ready for Delivery',
                        'out_for_delivery' => 'Out for Delivery',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple(),
                Tables\Filters\Filter::make('requested_delivery_date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('requested_delivery_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('requested_delivery_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Action::make('print preview')
                    ->label(null)
                    ->iconButton()
                    ->icon('heroicon-o-printer')
                    ->url(fn(Order $record): string => route('orders.print', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                // Action::make('mark_delivered')
                //     ->label('Mark Delivered')
                //     ->icon('heroicon-o-truck')
                //     ->color('success')
                //     ->action(fn(Order $record) => $record->update(['status' => 'delivered']))
                //     ->requiresConfirmation()
                //     ->hidden(fn(Order $record) => $record->status === 'delivered'),
                // Action::make('cancel_order')
                //     ->label('Cancel Order')
                //     ->icon('heroicon-o-x-circle')
                //     ->color('danger')
                //     ->action(fn(Order $record) => $record->update(['status' => 'cancelled']))
                //     ->requiresConfirmation()
                //     ->hidden(fn(Order $record) => in_array($record->status, ['delivered', 'cancelled'])),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
            'calendar' => Pages\DeliveryCalendar::route('/calendar'),

        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
