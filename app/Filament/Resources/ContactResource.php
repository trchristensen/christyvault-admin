<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ContactResource\Pages\ListContacts;
use App\Filament\Resources\ContactResource\Pages\CreateContact;
use App\Filament\Resources\ContactResource\Pages\EditContact;
use App\Filament\Resources\ContactResource\Pages;
use App\Models\Contact;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | \UnitEnum | null $navigationGroup = 'Directories';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contact Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Grid::make(2)
                            ->schema([
                                PhoneInput::make('phone')
                                    ->label('Office Phone')
                                    ->defaultCountry('US'),
                                TextInput::make('phone_extension')
                                    ->label('Extension')
                                    ->maxLength(10)
                                    ->placeholder('x1234'),
                            ]),
                        PhoneInput::make('mobile_phone')
                            ->label('Mobile Phone')
                            ->defaultCountry('US'),
                        TextInput::make('title')
                            ->maxLength(255),
                        Select::make('contact_types')
                            ->relationship('contactTypes', 'name')
                            ->multiple()
                            ->preload()
                            ->native(false),
                        Select::make('locations')
                            ->relationship(
                                name: 'locations',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn($query) => $query->select(['locations.id', 'locations.name', 'locations.address_line1', 'locations.city', 'locations.state', 'locations.postal_code'])
                            )
                            ->multiple()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn($record) => "
                                    <div class='font-medium'>{$record->name}</div>
                                    <div class='text-sm text-gray-500'>{$record->address_line1}, {$record->city}, {$record->state}</div>
                                ")
                            ->allowHtml(),
                        Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')
                ->searchable()
                ->sortable(),

            TextColumn::make('locations.name')
                ->badge()
                ->separator(',')
                ->searchable(query: function ($query, $search) {
                    return $query->whereHas('locations', function ($query) use ($search) {
                        $query->where('locations.name', 'like', "%{$search}%");
                    });
                }),

            TextColumn::make('phone')
                ->searchable(),

            TextColumn::make('mobile_phone')
                ->searchable(),

            TextColumn::make('title')
                ->searchable(),

            TextColumn::make('email')
                ->searchable()
                ->sortable(),

            TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('locations')
                    ->relationship('locations', 'name', fn($query) => $query->select(['locations.id as id', 'locations.name as name'])->orderBy('locations.name'))
                    ->multiple()
                    ->preload()
                    ->searchable(),

                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->boolean()
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->native(false),
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

    public static function getPages(): array
    {
        return [
            'index' => ListContacts::route('/'),
            'create' => CreateContact::route('/create'),
            'edit' => EditContact::route('/{record}/edit'),
        ];
    }
}
