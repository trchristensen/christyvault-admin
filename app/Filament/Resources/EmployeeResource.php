<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use App\Filament\Resources\EmployeeResource\Pages\ListEmployees;
use App\Filament\Resources\EmployeeResource\Pages\CreateEmployee;
use App\Filament\Resources\EmployeeResource\Pages\EditEmployee;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Models\Driver;
use App\Models\Employee;
use App\Models\User;
use Filament\Forms;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | \UnitEnum | null $navigationGroup = 'Directories';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Association')
                    ->description('Select an existing user account to link to this employee')
                    ->schema([
                        Select::make('user_id')
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

                Section::make('Employee Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->readOnly(fn(Get $get): bool => (bool) $get('user_id')),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->readOnly(fn(Get $get): bool => (bool) $get('user_id')),
                        PhoneInput::make('phone')->defaultCountry('US'),
                        TextInput::make('address'),
                        Select::make('positions')
                            ->relationship(
                                name: 'positions',
                                titleAttribute: 'display_name',
                            )
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->required()
                            ->live()
                            ->dehydrated(true),


                        Select::make('christy_location')
                            ->options([
                                'colma' => 'Colma',
                                'tulare' => 'Tulare',
                            ])
                            ->required(),
                        DatePicker::make('hire_date')
                            ->required(),
                        DatePicker::make('birth_date')
                            ->label('Birthdate')
                            ->required(),
                        Checkbox::make('is_active')
                            ->default('TRUE')
                            ->label('Active')
                            ->dehydrateStateUsing(fn($state) => $state ? 'TRUE' : 'FALSE'),
                    ])->columns(2),

                Section::make('Driver Details')
                    ->schema([
                        TextInput::make('driver.license_number')
                            ->label('License Number'),
                        DatePicker::make('driver.license_expiration')
                            ->label('License Expiration'),
                        Textarea::make('driver.notes')
                            ->label('Notes'),
                    ])
                    ->visible(function (Get $get): bool {
                        return collect($get('positions'))->contains(
                            fn($position) =>
                            $position === 'driver' ||
                                (is_array($position) && ($position['name'] ?? null) === 'driver')
                        );
                    })
                    ->columns(2),
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
                PhoneColumn::make('phone')
                    ->displayFormat(PhoneInputNumberType::INTERNATIONAL),
                TextColumn::make('positions.display_name')
                    ->badge()
                    ->separator(',')
                    ->searchable(),
                TextColumn::make('christy_location')
                    ->label('Location')
                    ->formatStateUsing(fn(string $state): string => ucfirst($state))
                    ->badge(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Group::make('christy_location')
                    ->label('Location')
                    ->getTitleFromRecordUsing(fn(Employee $record): string => ucfirst($record->christy_location))
                    ->collapsible(),
                Group::make('positions.name')
                    ->label('Position')
                    ->getTitleFromRecordUsing(fn(Employee $record): string => $record->positions->pluck('display_name')->join(', '))
                    ->collapsible(),
            ])
            ->defaultGroup('christy_location')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $employee = static::getModel()::create($data);

        // If positions include driver, create driver record
        if (isset($data['positions']) && in_array('driver', $data['positions'])) {
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
