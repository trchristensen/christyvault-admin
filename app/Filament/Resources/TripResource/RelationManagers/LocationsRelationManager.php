<?php

namespace App\Filament\Resources\TripResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'locations';
    protected static ?string $title = 'Trip Locations';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->options([
                        'start_location' => 'Start Location',
                        'delivery' => 'Delivery Location',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('sequence')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('location_type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'cemetery' => 'danger',
                        'funeral_home' => 'warning',
                        'other' => 'gray',
                    }),
                Tables\Columns\TextColumn::make('full_address')
                    ->label('Address'),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'start_location' => 'success',
                        'delivery' => 'info',
                    }),
                Tables\Columns\TextColumn::make('sequence')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn(Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Select::make('type')
                            ->options([
                                'start_location' => 'Start Location',
                                'delivery' => 'Delivery Location',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('sequence')
                            ->numeric()
                            ->default(0)
                            ->required(),
                    ]),
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
