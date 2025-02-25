<?php

namespace App\Filament\Sales\Resources;

use App\Filament\Sales\Resources\ContactResource\Pages;
use App\Models\Contact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('phone')
                ->tel()
                ->maxLength(20),

            Forms\Components\TextInput::make('title')
                ->maxLength(255),

            Forms\Components\Select::make('locations')
                ->relationship('locations', 'name')
                ->multiple()
                ->preload()
                ->searchable(),

            Forms\Components\Toggle::make('is_active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('email')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('phone')
                ->searchable(),

            Tables\Columns\TextColumn::make('title')
                ->searchable(),

            Tables\Columns\TextColumn::make('locations.name')
                ->badge()
                ->separator(',')
                ->searchable(),

            Tables\Columns\IconColumn::make('is_active')
                ->boolean()
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
