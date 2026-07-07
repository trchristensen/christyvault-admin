<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CalendarDayResource\Pages\ListCalendarDays;
use App\Filament\Resources\CalendarDayResource\Pages\CreateCalendarDay;
use App\Filament\Resources\CalendarDayResource\Pages\EditCalendarDay;
use App\Filament\Resources\CalendarDayResource\Pages;
use App\Models\CalendarDay;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CalendarDayResource extends Resource
{
    protected static ?string $model = CalendarDay::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string | \UnitEnum | null $navigationGroup = 'Delivery Management';

    protected static ?string $navigationLabel = 'Calendar Days';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Calendar Day')
                    ->schema([
                        DatePicker::make('date')
                            ->required()
                            ->native(false),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('type')
                            ->options(CalendarDay::typeOptions())
                            ->default(CalendarDay::TYPE_HOLIDAY)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
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
                        Toggle::make('blocks_delivery')
                            ->label('Blocks delivery')
                            ->default(true)
                            ->helperText('Prevents orders and trips from being scheduled on this date.'),
                        Toggle::make('opens_delivery')
                            ->label('Opens delivery')
                            ->helperText('Overrides the default weekend block for this date.'),
                        Textarea::make('notes')
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
                TextColumn::make('date')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type_label')
                    ->label('Type')
                    ->badge()
                    ->sortable(query: fn($query, string $direction) => $query->orderBy('type', $direction)),
                IconColumn::make('blocks_delivery')
                    ->label('Blocks')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('opens_delivery')
                    ->label('Opens')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('date')
            ->filters([
                SelectFilter::make('type')
                    ->options(CalendarDay::typeOptions()),
                TernaryFilter::make('blocks_delivery')
                    ->label('Blocks delivery'),
                TernaryFilter::make('opens_delivery')
                    ->label('Opens delivery'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalendarDays::route('/'),
            'create' => CreateCalendarDay::route('/create'),
            'edit' => EditCalendarDay::route('/{record}/edit'),
        ];
    }
}
