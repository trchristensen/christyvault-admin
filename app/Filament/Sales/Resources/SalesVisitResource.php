<?php

namespace App\Filament\Sales\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Sales\Resources\SalesVisitResource\Pages\ListSalesVisits;
use App\Filament\Sales\Resources\SalesVisitResource\Pages\CreateSalesVisit;
use App\Filament\Sales\Resources\SalesVisitResource\Pages\EditSalesVisit;
use App\Enums\SalesVisitStatus;
use App\Filament\Sales\Resources\SalesVisitResource\Pages;
use App\Models\SalesVisit;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Contact;

class SalesVisitResource extends Resource
{
    protected static ?string $model = SalesVisit::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';
    protected static string | \UnitEnum | null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('location_id')
                ->relationship('location', 'name')
                ->required()
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(fn($state, Set $set) =>
                $set('contact_id', null)),

            Select::make('contact_id')
                ->relationship(
                    name: 'contact',
                    titleAttribute: 'name'
                )
                ->options(function (Get $get) {
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
                    TextInput::make('name')->required(),
                    TextInput::make('email')->required()->email(),
                    TextInput::make('phone'),
                    TextInput::make('title'),
                ])
                ->visible(fn(Get $get) => filled($get('location_id'))),

            Select::make('employee_id')
                ->relationship('employee', 'name')
                ->required()
                ->searchable()
                ->preload(),

            DateTimePicker::make('planned_at')
                ->required()
                ->native(false),

            DateTimePicker::make('completed_at')
                ->native(false),

            Select::make('status')
                ->options(collect(SalesVisitStatus::cases())->mapWithKeys(
                    fn($status) =>
                    [$status->value => $status->getLabel()]
                ))
                ->default(SalesVisitStatus::PLANNED->value)
                ->required(),

            Textarea::make('visit_notes')
                ->label('Internal Notes')
                ->columnSpanFull(),

            Textarea::make('followup_summary')
                ->label('Follow-up Summary (will be sent in email)')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('location.name')
                ->searchable()
                ->sortable(),

            TextColumn::make('contact.name')
                ->searchable()
                ->sortable(),

            TextColumn::make('employee.name')
                ->searchable()
                ->sortable(),

            TextColumn::make('planned_at')
                ->dateTime()
                ->sortable(),

            TextColumn::make('status')
                ->badge()
                ->color(fn(SalesVisit $record) => $record->status->getColor()),

            TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
            ->defaultSort('planned_at')
            ->filters([
                SelectFilter::make('location')
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->options(collect(SalesVisitStatus::cases())->mapWithKeys(
                        fn($status) =>
                        [$status->value => $status->getLabel()]
                    )),

                SelectFilter::make('employee')
                    ->relationship('employee', 'name'),
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
            'index' => ListSalesVisits::route('/'),
            'create' => CreateSalesVisit::route('/create'),
            'edit' => EditSalesVisit::route('/{record}/edit'),
        ];
    }
}
