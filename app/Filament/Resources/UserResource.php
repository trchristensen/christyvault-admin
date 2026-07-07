<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\CheckboxList;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Enums\PlantLocation;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | \UnitEnum | null $navigationGroup = 'Directories';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Details')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('email')
                            ->email()
                            ->required(),
                        DateTimePicker::make('email_verified_at')
                            ->disabled(),
                        TextInput::make('password')
                            ->password()
                            ->dehydrated(fn($state) => filled($state))
                            ->required(fn(string $context): bool => $context === 'create'),
                    ])->columns(2),

                Section::make('Team Schedule Access')
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('team_schedule_delivery_types')
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
                        TextInput::make('team_schedule_days_ahead')
                            ->label('Days Ahead')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(90)
                            ->placeholder('14')
                            ->helperText('Leave blank to use 14 days.'),
                    ])
                    ->columns(2),

                Section::make('Roles & Relationships')
                    ->columnSpanFull()
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
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('employee.name')
                    ->label('Associated Employee')
                    ->searchable(),
                TextColumn::make('employee.position')
                    ->label('Position'),
                TextColumn::make('roles.name')
                    ->badge()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
