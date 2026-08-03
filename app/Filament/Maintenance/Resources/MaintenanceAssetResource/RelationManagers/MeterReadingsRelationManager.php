<?php

namespace App\Filament\Maintenance\Resources\MaintenanceAssetResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MeterReadingsRelationManager extends RelationManager
{
    protected static string $relationship = 'meterReadings';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('reading')->numeric()->minValue(0)->required(),
            DateTimePicker::make('recorded_at')->default(now())->required(),
            Select::make('source')->options(['manual' => 'Manual', 'inspection' => 'Inspection', 'telematics' => 'Telematics / import'])->default('manual')->required(),
            Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('reading')->numeric(decimalPlaces: 2),
            TextColumn::make('recorded_at')->dateTime()->sortable(),
            TextColumn::make('recordedBy.name')->label('Recorded by'),
            TextColumn::make('source')->badge(),
            TextColumn::make('notes')->wrap(),
        ])->defaultSort('recorded_at', 'desc')->headerActions([
            CreateAction::make()->mutateDataUsing(function (array $data): array {
                $data['recorded_by_user_id'] = auth()->id();

                return $data;
            }),
        ]);
    }
}
