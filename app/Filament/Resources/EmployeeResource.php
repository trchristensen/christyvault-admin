<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages\CreateEmployee;
use App\Filament\Resources\EmployeeResource\Pages\EditEmployee;
use App\Filament\Resources\EmployeeResource\Pages\ListEmployees;
use App\Models\Driver;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Ysfkaya\FilamentPhoneInput\PhoneInputNumberType;
use Ysfkaya\FilamentPhoneInput\Tables\PhoneColumn;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Directories';

    protected static ?string $navigationParentItem = 'People';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Employee Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Optional. This is contact information, not the employee\'s login.'),
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
                            ->helperText('Positions describe job responsibilities. User roles separately control application access.')
                            ->dehydrated(true),

                        Select::make('christy_location')
                            ->options([
                                'colma' => 'Colma',
                                'tulare' => 'Tulare',
                            ])
                            ->required(),
                        DatePicker::make('hire_date')
                            ->helperText('Optional. Add it when the official date is known.'),
                        DatePicker::make('birth_date')
                            ->label('Birthdate')
                            ->helperText('Optional.'),
                        Checkbox::make('is_active')
                            ->default(true)
                            ->label('Active'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Account Access')
                    ->description('Optional. Link an existing user when this employee needs to sign in. This can be done later.')
                    ->schema([
                        Select::make('user_id')
                            ->label('User Account')
                            ->relationship(
                                name: 'user',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, ?Employee $record): Builder => $query
                                    ->where(function (Builder $query) use ($record): void {
                                        $query->whereDoesntHave('employee')
                                            ->when(
                                                $record?->user_id,
                                                fn (Builder $query, int $userId): Builder => $query->orWhereKey($userId),
                                            );
                                    }),
                            )
                            ->preload()
                            ->searchable()
                            ->unique(ignoreRecord: true)
                            ->placeholder('No account linked')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                if (! $state) {
                                    return;
                                }

                                $user = User::find($state);
                                if (! $user) {
                                    return;
                                }

                                if (empty($get('name'))) {
                                    $set('name', $user->name);
                                }
                                if (empty($get('email'))) {
                                    $set('email', $user->email);
                                }
                            }),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),

                Section::make('Driver License Details')
                    ->description('Optional. Trips use the Driver position; these fields are only for license information you want to retain.')
                    ->schema([
                        TextInput::make('driver.license_number')
                            ->label('License Number'),
                        DatePicker::make('driver.license_expiration')
                            ->label('License Expiration'),
                        Textarea::make('driver.notes')
                            ->label('Notes'),
                    ])
                    ->visible(fn (Get $get, ?Employee $record): bool => static::selectedPositionsIncludeDriver($get('positions') ?? []) ||
                        $record?->driver()->exists()
                    )
                    ->columns(2)
                    ->columnSpanFull(),
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
                IconColumn::make('user_id')
                    ->label('Account')
                    ->boolean()
                    ->state(fn (Employee $record): bool => filled($record->user_id))
                    ->tooltip(fn (Employee $record): string => filled($record->user_id) ? 'User account linked' : 'No user account linked'),
                TextColumn::make('positions.display_name')
                    ->badge()
                    ->separator(',')
                    ->searchable(),
                TextColumn::make('christy_location')
                    ->label('Location')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
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
                    ->getTitleFromRecordUsing(fn (Employee $record): string => ucfirst($record->christy_location))
                    ->collapsible(),
                Group::make('positions.name')
                    ->label('Position')
                    ->getTitleFromRecordUsing(fn (Employee $record): string => $record->positions->pluck('display_name')->join(', '))
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
        if (
            static::selectedPositionsIncludeDriver($data['positions'] ?? []) &&
            static::hasDriverDetails($data['driver'] ?? [])
        ) {
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

    /**
     * @param  array<int, int|string|array<string, mixed>>  $positions
     */
    public static function selectedPositionsIncludeDriver(array $positions): bool
    {
        $positionIds = collect($positions)
            ->map(fn ($position) => is_array($position) ? ($position['id'] ?? null) : $position)
            ->filter()
            ->all();

        return $positionIds !== [] && Position::query()
            ->whereKey($positionIds)
            ->where('name', 'driver')
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $driverData
     */
    public static function hasDriverDetails(array $driverData): bool
    {
        return collect($driverData)
            ->only(['license_number', 'license_expiration', 'notes'])
            ->contains(fn ($value): bool => filled($value));
    }
}
