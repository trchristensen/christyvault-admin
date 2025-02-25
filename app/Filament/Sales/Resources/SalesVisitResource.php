<?php

namespace App\Filament\Sales\Resources;

use App\Enums\SalesVisitStatus;
use App\Filament\Sales\Resources\SalesVisitResource\Pages;
use App\Models\SalesVisit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Contact;

class SalesVisitResource extends Resource
{
    protected static ?string $model = SalesVisit::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('location_id')
                ->relationship('location', 'name')
                ->required()
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(fn($state, Forms\Set $set) =>
                $set('contact_id', null)),

            Forms\Components\Select::make('contact_id')
                ->relationship(
                    name: 'contact',
                    titleAttribute: 'name'
                )
                ->options(function (Forms\Get $get) {
                    if (!$get('location_id')) {
                        return [];
                    }

                    return Contact::query()
                        ->whereHas(
                            'locations',
                            fn($query) =>
                            $query->where('locations.id', $get('location_id'))
                        )
                        ->get()
                        ->mapWithKeys(
                            fn($contact) =>
                            [$contact->id => $contact->name_with_title]
                        );
                })
                ->label('Contact')
                ->required()
                ->searchable()
                ->preload()
                ->createOptionForm([
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\TextInput::make('email')->required()->email(),
                    Forms\Components\TextInput::make('phone'),
                    Forms\Components\TextInput::make('title'),
                ])
                ->visible(fn(Forms\Get $get) => filled($get('location_id'))),

            Forms\Components\Select::make('employee_id')
                ->relationship('employee', 'name')
                ->required()
                ->searchable()
                ->preload(),

            Forms\Components\DateTimePicker::make('planned_at')
                ->required()
                ->native(false),

            Forms\Components\DateTimePicker::make('completed_at')
                ->native(false),

            Forms\Components\Select::make('status')
                ->options(collect(SalesVisitStatus::cases())->mapWithKeys(
                    fn($status) =>
                    [$status->value => $status->getLabel()]
                ))
                ->default(SalesVisitStatus::PLANNED->value)
                ->required(),

            Forms\Components\Textarea::make('visit_notes')
                ->label('Internal Notes')
                ->columnSpanFull(),

            Forms\Components\Textarea::make('followup_summary')
                ->label('Follow-up Summary (will be sent in email)')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('location.name')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('contact.name')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('employee.name')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('planned_at')
                ->dateTime()
                ->sortable(),

            Tables\Columns\TextColumn::make('status')
                ->badge()
                ->color(fn(SalesVisit $record) => $record->status->getColor()),

            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
            ->defaultSort('planned_at')
            ->filters([
                Tables\Filters\SelectFilter::make('location')
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(SalesVisitStatus::cases())->mapWithKeys(
                        fn($status) =>
                        [$status->value => $status->getLabel()]
                    )),

                Tables\Filters\SelectFilter::make('employee')
                    ->relationship('employee', 'name'),
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
            'index' => Pages\ListSalesVisits::route('/'),
            'create' => Pages\CreateSalesVisit::route('/create'),
            'edit' => Pages\EditSalesVisit::route('/{record}/edit'),
        ];
    }
}
