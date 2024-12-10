<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'locations';
    protected static ?string $title = 'Customer Locations';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Section::make('Address')
                    ->schema([
                        Forms\Components\TextInput::make('address_line1')
                            ->required()
                            ->label('Address Line 1'),
                        Forms\Components\TextInput::make('address_line2')
                            ->label('Address Line 2'),
                        Forms\Components\TextInput::make('city')
                            ->required(),
                        Forms\Components\TextInput::make('state')
                            ->required(),
                        Forms\Components\TextInput::make('postal_code')
                            ->required(),
                        Forms\Components\TextInput::make('country')
                            ->default('USA')
                            ->required(),
                    ])->columns(2),
                Forms\Components\Section::make('Coordinates')
                    ->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->numeric()
                            ->rules(['nullable', 'numeric', 'between:-90,90'])
                            ->step(0.000001)
                            ->placeholder('e.g. 41.878113'),
                        Forms\Components\TextInput::make('longitude')
                            ->numeric()
                            ->rules(['nullable', 'numeric', 'between:-180,180'])
                            ->step(0.000001)
                            ->placeholder('e.g. -87.629799'),
                    ])->columns(2),
                Forms\Components\Select::make('location_type')
                    ->options([
                        'cemetery' => 'Cemetery',
                        'section' => 'Cemetery Section',  // for specific yards/sections
                        'funeral_home' => 'Funeral Home',
                        'other' => 'Other',
                    ])
                    ->default('business')
                    ->required(),
                Forms\Components\Select::make('type')
                    ->options([
                        'primary' => 'Primary',
                        'billing' => 'Billing',
                        'shipping' => 'Shipping',
                    ])
                    ->default('primary')
                    ->required(),
                Forms\Components\TextInput::make('sequence')
                    ->numeric()
                    ->default(0)
                    ->hidden(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location_type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'cemetery' => 'danger',
                        'section' => 'warning',
                        'funeral_home' => 'info',
                        'other' => 'gray',
                    }),
                Tables\Columns\TextColumn::make('full_address')
                    ->label('Address'),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'primary' => 'success',
                        'billing' => 'warning',
                        'shipping' => 'info',
                        default => 'gray',
                    }),
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
                                'primary' => 'Primary',
                                'billing' => 'Billing',
                                'shipping' => 'Shipping',
                            ])
                            ->default('primary')
                            ->required(),
                        Forms\Components\TextInput::make('sequence')
                            ->numeric()
                            ->default(0)
                            ->hidden(),
                    ]),
                Tables\Actions\CreateAction::make()
                    ->visible(fn ($livewire) => !$livewire->getOwnerRecord()->locations()->exists()),
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
