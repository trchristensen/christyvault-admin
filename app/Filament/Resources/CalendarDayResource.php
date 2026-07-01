<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CalendarDayResource\Pages;
use App\Models\CalendarDay;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CalendarDayResource extends Resource
{
    protected static ?string $model = CalendarDay::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Delivery Management';

    protected static ?string $navigationLabel = 'Calendar Days';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Calendar Day')
                    ->schema([
                        Forms\Components\DatePicker::make('date')
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('type')
                            ->options(CalendarDay::typeOptions())
                            ->default(CalendarDay::TYPE_HOLIDAY)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Forms\Set $set): void {
                                if ($state === CalendarDay::TYPE_SPECIAL_OPEN_DAY) {
                                    $set('blocks_delivery', false);
                                    $set('opens_delivery', true);

                                    return;
                                }

                                if ($state === CalendarDay::TYPE_NOTE) {
                                    $set('blocks_delivery', false);
                                    $set('opens_delivery', false);

                                    return;
                                }

                                $set('blocks_delivery', true);
                                $set('opens_delivery', false);
                            }),
                        Forms\Components\Toggle::make('blocks_delivery')
                            ->label('Blocks delivery')
                            ->default(true)
                            ->helperText('Prevents orders and trips from being scheduled on this date.'),
                        Forms\Components\Toggle::make('opens_delivery')
                            ->label('Opens delivery')
                            ->helperText('Overrides the default weekend block for this date.'),
                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull()
                            ->maxLength(65535),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type_label')
                    ->label('Type')
                    ->badge()
                    ->sortable(query: fn($query, string $direction) => $query->orderBy('type', $direction)),
                Tables\Columns\IconColumn::make('blocks_delivery')
                    ->label('Blocks')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('opens_delivery')
                    ->label('Opens')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('date')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(CalendarDay::typeOptions()),
                Tables\Filters\TernaryFilter::make('blocks_delivery')
                    ->label('Blocks delivery'),
                Tables\Filters\TernaryFilter::make('opens_delivery')
                    ->label('Opens delivery'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCalendarDays::route('/'),
            'create' => Pages\CreateCalendarDay::route('/create'),
            'edit' => Pages\EditCalendarDay::route('/{record}/edit'),
        ];
    }
}
