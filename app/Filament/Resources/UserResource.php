<?php

namespace App\Filament\Resources;

use App\Enums\PlantLocation;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Directories';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required(),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required(),
                        Forms\Components\DateTimePicker::make('email_verified_at')
                            ->disabled(),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrated(fn($state) => filled($state))
                            ->required(fn(string $context): bool => $context === 'create'),
                    ])->columns(2),

                Forms\Components\Section::make('Team Schedule Access')
                    ->schema([
                        Forms\Components\CheckboxList::make('team_schedule_delivery_types')
                            ->label('Visible Delivery Types')
                            ->options(
                                collect(PlantLocation::cases())
                                    ->mapWithKeys(fn(PlantLocation $location) => [
                                        $location->value => $location->getLabel(),
                                    ])
                                    ->toArray()
                            )
                            ->helperText('Leave blank to show all delivery types.')
                            ->columns(3),
                        Forms\Components\TextInput::make('team_schedule_days_ahead')
                            ->label('Days Ahead')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(90)
                            ->placeholder('14')
                            ->helperText('Leave blank to use 14 days.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Roles & Relationships')
                    ->schema([
                        Select::make('roles')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->preload(),
                        Select::make('employee.id')  // Change from employee_id to employee.id
                            ->label('Associated Employee')
                            ->relationship('employee', 'name')
                            ->preload()
                            ->disabled()

                    ])->columns(2),
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Associated Employee')
                    ->searchable(),
                Tables\Columns\TextColumn::make('employee.position')
                    ->label('Position'),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
