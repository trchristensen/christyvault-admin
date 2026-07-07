<?php

namespace App\Filament\Resources\TripResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\AttachAction;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'locations';
    protected static ?string $title = 'Trip Locations';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options([
                        'start_location' => 'Start Location',
                        'delivery' => 'Delivery Location',
                    ])
                    ->required(),
                TextInput::make('sequence')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable()
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('location_type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'cemetery' => 'danger',
                        'section' => 'warning',
                        'funeral_home' => 'info',
                        'other' => 'gray',
                    }),
                TextColumn::make('full_address')
                    ->label('Address'),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'start_location' => 'success',
                        'delivery' => 'info',
                    }),
                TextColumn::make('sequence')
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn(AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('type')
                            ->options([
                                'start_location' => 'Start Location',
                                'delivery' => 'Delivery Location',
                            ])
                            ->required(),
                        TextInput::make('sequence')
                            ->numeric()
                            ->default(0)
                            ->required(),
                    ]),
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
