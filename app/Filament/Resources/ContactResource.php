<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Models\Contact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Directories';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Contact Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                PhoneInput::make('phone')
                                    ->label('Office Phone')
                                    ->defaultCountry('US'),
                                Forms\Components\TextInput::make('phone_extension')
                                    ->label('Extension')
                                    ->maxLength(10)
                                    ->placeholder('x1234'),
                            ]),
                        PhoneInput::make('mobile_phone')
                            ->label('Mobile Phone')
                            ->defaultCountry('US'),
                        Forms\Components\TextInput::make('title')
                            ->maxLength(255),
                        Forms\Components\Select::make('contact_types')
                            ->relationship('contactTypes', 'name')
                            ->multiple()
                            ->preload()
                            ->native(false),
                        Forms\Components\Select::make('locations')
                            ->relationship('locations', 'name')
                            ->multiple()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn($record) => "
                                    <div class='font-medium'>{$record->name}</div>
                                    <div class='text-sm text-gray-500'>{$record->full_address}</div>
                                ")
                            ->allowHtml()
                            ->searchable(['name', 'address_line1', 'city', 'state', 'postal_code']),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('locations.name')
                ->badge()
                ->separator(',')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('phone')
                ->searchable(),

            Tables\Columns\TextColumn::make('mobile_phone')
                ->searchable(),

            Tables\Columns\TextColumn::make('title')
                ->searchable(),

            Tables\Columns\TextColumn::make('email')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('locations')
                    ->relationship('locations', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->boolean()
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->native(false),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContacts::route('/'),
            'create' => Pages\CreateContact::route('/create'),
            'edit' => Pages\EditContact::route('/{record}/edit'),
        ];
    }
}
