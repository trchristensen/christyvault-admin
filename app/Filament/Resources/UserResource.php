<?php

namespace App\Filament\Resources;

use App\Enums\PlantLocation;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use STS\FilamentImpersonate\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Directories';

    protected static ?string $navigationParentItem = 'People';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Details')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Display Name')
                            ->helperText('The name shown throughout the application. This can be a preferred name or nickname.')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Login Email')
                            ->email()
                            ->required(),
                        DateTimePicker::make('email_verified_at')
                            ->disabled(),
                        TextInput::make('password')
                            ->label('Testing Password')
                            ->password()
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('Optional local/testing fallback. Production users sign in with a magic link.')
                            ->visible(fn (): bool => app()->environment(['local', 'testing'])),
                    ])->columns(2),

                Section::make('Access')
                    ->description('Roles control which application panels and features this account can use.')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('roles')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->preload(),
                    ]),

                Section::make('Employee Settings')
                    ->description('These settings apply only because this account is linked to an employee profile.')
                    ->columnSpanFull()
                    ->visible(fn (?User $record): bool => $record?->employee !== null)
                    ->schema([
                        Select::make('employee.id')
                            ->label('Associated Employee')
                            ->relationship('employee', 'name')
                            ->helperText('Employee links are managed from the employee record.')
                            ->preload()
                            ->disabled()
                            ->columnSpanFull(),
                        CheckboxList::make('team_schedule_delivery_types')
                            ->label('Visible Delivery Types')
                            ->options(
                                collect(PlantLocation::cases())
                                    ->mapWithKeys(fn (PlantLocation $location) => [
                                        $location->value => $location->getLabel(),
                                    ])
                                    ->toArray()
                            )
                            ->helperText('Applies only when this employee has permission to view the delivery schedule. Leave blank to show all delivery types.')
                            ->columns(3)
                            ->columnSpanFull(),
                        TextInput::make('team_schedule_days_ahead')
                            ->label('Days Ahead')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(90)
                            ->placeholder('14')
                            ->helperText('Leave blank to use 14 days.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Display Name')
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
                Impersonate::make()
                    ->redirectTo(fn (User $record): string => $record->getPreferredPanelUrl() ?? static::getUrl('index'))
                    ->backTo(static::getUrl('index'))
                    ->withoutSpa(),
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
