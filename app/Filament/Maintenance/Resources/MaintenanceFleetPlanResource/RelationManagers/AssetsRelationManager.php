<?php

namespace App\Filament\Maintenance\Resources\MaintenanceFleetPlanResource\RelationManagers;

use App\Models\MaintenanceFleetPlanAsset;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Fleet assets and service baselines';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('included')->helperText('Turn this off for a low-use unit that should not receive group service.'),
            TextInput::make('baseline_meter')
                ->label('Last group-service meter')
                ->numeric()
                ->minValue(0)
                ->live()
                ->afterStateUpdated(fn ($state, Set $set) => $set(
                    'next_due_meter',
                    filled($state) ? (float) $state + (float) $this->getOwnerRecord()->meter_interval : null,
                )),
            TextInput::make('next_due_meter')->label('Next due meter')->numeric()->minValue(0)->helperText('Normally the last group-service meter plus the plan interval.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('asset.asset_tag')->label('Asset')->searchable()->sortable(),
            TextColumn::make('asset.name')->label('Equipment')->wrap(),
            TextColumn::make('asset.current_meter')->label('Current meter')->numeric(decimalPlaces: 1)->placeholder('Missing'),
            TextColumn::make('baseline_meter')->label('Last service')->numeric(decimalPlaces: 1)->placeholder('Missing'),
            TextColumn::make('next_due_meter')->label('Next due')->numeric(decimalPlaces: 1)->placeholder('Needs baseline'),
            TextColumn::make('remaining')->label('Hours remaining')->state(fn (MaintenanceFleetPlanAsset $record): ?float => $record->next_due_meter !== null && $record->asset?->current_meter !== null
                ? (float) $record->next_due_meter - (float) $record->asset->current_meter
                : null)->numeric(decimalPlaces: 1)->placeholder('—')->color(fn ($state) => $state !== null && $state <= 0 ? 'danger' : null),
            IconColumn::make('included')->boolean(),
            IconColumn::make('matches_filter')->label('Matches rules')->boolean(),
        ])->recordActions([EditAction::make()]);
    }
}
