<?php

namespace App\Filament\Team\Resources;

use App\Filament\Team\Resources\EventResource\Pages;
use App\Filament\Team\Resources\EventResource\RelationManagers;
use App\Models\Event;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->maxLength(65535),
                Forms\Components\DateTimePicker::make('start')
                    ->required(),
                Forms\Components\DateTimePicker::make('end'),
                Forms\Components\Checkbox::make('all_day'),
                Forms\Components\ColorPicker::make('color'),
                Forms\Components\Select::make('type')
                    ->options([
                        'holiday' => 'Holiday',
                        'time_off' => 'Time Off',
                        'company_event' => 'Company Event',
                    ])
                    ->required(),
                // Forms\Components\Select::make('employee_id')
                //     ->relationship('employee', 'name')
                //     ->visible(fn(Forms\Get $get) => $get('type') === 'time_off'),
                // Forms\Components\Select::make('status')
                //     ->options([
                //         'pending' => 'Pending',
                //         'approved' => 'Approved',
                //         'rejected' => 'Rejected',
                //     ])
                //     ->visible(fn(Forms\Get $get) => $get('type') === 'time_off'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\TextColumn::make('start')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('end')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('type'),
                // Tables\Columns\TextColumn::make('employee.name')
                //     ->visible(fn(Event $record) => $record->type === 'time_off'),
                // Tables\Columns\TextColumn::make('status')
                //     ->visible(fn(Event $record) => $record->type === 'time_off'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
