<?php

namespace App\Filament\Maintenance\Resources\MaintenanceAssetResource\RelationManagers;

use App\Filament\Maintenance\Resources\MaintenancePlanResource;
use App\Models\MaintenancePlan;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlansRelationManager extends RelationManager
{
    protected static string $relationship = 'plans';

    protected static ?string $title = 'Preventive maintenance plans';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('trigger_type')->badge(),
            TextColumn::make('next_due_date')->date()->placeholder('Meter-based'),
            TextColumn::make('next_due_meter')->numeric()->placeholder('Calendar-based'),
            IconColumn::make('active')->boolean(),
        ])->recordActions([
            Action::make('open')->icon('heroicon-o-arrow-top-right-on-square')->url(fn (MaintenancePlan $record) => MaintenancePlanResource::getUrl('edit', ['record' => $record])),
        ]);
    }
}
