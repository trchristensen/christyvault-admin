<?php

namespace App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LaborEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'laborEntries';

    protected static ?string $title = 'Labor and timer entries';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->relationship('user', 'name')->searchable()->preload(),
            DateTimePicker::make('started_at')->required(),
            DateTimePicker::make('ended_at'),
            TextInput::make('minutes')->numeric()->integer()->minValue(0),
            Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.name')->label('Technician')->placeholder('Unknown'),
            TextColumn::make('started_at')->dateTime()->sortable(),
            TextColumn::make('ended_at')->dateTime()->placeholder('Running'),
            TextColumn::make('minutes')->formatStateUsing(fn ($state) => filled($state) ? round($state / 60, 2).' hr' : 'Running'),
            TextColumn::make('notes')->wrap(),
        ])->headerActions([CreateAction::make()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
