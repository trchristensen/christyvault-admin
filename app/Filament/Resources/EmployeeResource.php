<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Models\Driver;
use App\Models\Employee;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Navigation\NavigationGroup;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Ysfkaya\FilamentPhoneInput\Infolists\PhoneEntry;
use Ysfkaya\FilamentPhoneInput\PhoneInputNumberType;
use Ysfkaya\FilamentPhoneInput\Tables\PhoneColumn;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Directories';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Association')
                    ->description('Select an existing user account to link to this employee')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Associated User Account')
                            ->relationship('user', 'name')
                            ->preload()
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                if (!$state) return;

                                $user = User::find($state);
                                if (!$user) return;

                                // Only set values if they're empty
                                if (empty($get('name'))) {
                                    $set('name', $user->name);
                                }
                                if (empty($get('email'))) {
                                    $set('email', $user->email);
                                }
                            }),
                    ])->columnSpanFull(),

                Forms\Components\Section::make('Employee Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->readOnly(fn(Get $get): bool => (bool) $get('user_id')),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->readOnly(fn(Get $get): bool => (bool) $get('user_id')),
                        PhoneInput::make('phone')->defaultCountry('US'),
                        Forms\Components\TextInput::make('address'),
                        Forms\Components\Select::make('position')
                            ->options([
                                'driver' => 'Driver',
                                'production' => 'Production',
                                'foreman' => 'Foreman',
                                'manager' => 'Manager',
                            ])
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('christy_location')
                            ->options([
                                'colma' => 'Colma',
                                'tulare' => 'Tulare',
                            ])
                            ->required(),
                        Forms\Components\DatePicker::make('hire_date')
                            ->required(),
                        Forms\Components\DatePicker::make('birth_date')
                            ->label('Birthdate')
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                    ])->columns(2),

                Forms\Components\Section::make('Driver Details')
                    ->schema([
                        Forms\Components\TextInput::make('driver.license_number')
                            ->label('License Number'),
                        Forms\Components\DatePicker::make('driver.license_expiration')
                            ->label('License Expiration'),
                        Forms\Components\Textarea::make('driver.notes')
                            ->label('Notes'),
                    ])
                    ->visible(fn(Get $get) => $get('position') === 'driver')
                    ->columns(2),
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
                PhoneColumn::make('phone')
                    ->displayFormat(PhoneInputNumberType::INTERNATIONAL),
                Tables\Columns\TextColumn::make('position')
                    ->searchable()
                    ->badge(),
                Tables\Columns\TextColumn::make('christy_location')
                    ->label('Location')
                    ->formatStateUsing(fn(string $state): string => ucfirst($state))
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Tables\Grouping\Group::make('christy_location')
                    ->label('Location')
                    ->getTitleFromRecordUsing(fn(Employee $record): string => ucfirst($record->christy_location))
                    ->collapsible(),
                Tables\Grouping\Group::make('position')
                    ->label('Position')
                    ->getTitleFromRecordUsing(fn(Employee $record): string => ucfirst($record->position))
                    ->collapsible(),
            ])
            ->defaultGroup('christy_location')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
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
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    protected static function handleRecordCreation(array $data): Model
    {
        // Remove user creation since we're selecting an existing user
        $employee = static::getModel()::create($data);

        // If position is driver, create driver record
        if ($data['position'] === 'driver') {
            $driverData = [
                'employee_id' => $employee->id,
                'license_number' => $data['driver']['license_number'] ?? null,
                'license_expiration' => $data['driver']['license_expiration'] ?? null,
                'notes' => $data['driver']['notes'] ?? null,
            ];
            Driver::create($driverData);
        }

        return $employee;
    }
}
