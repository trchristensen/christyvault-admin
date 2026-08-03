<?php

namespace App\Filament\Maintenance\Resources;

use App\Filament\Maintenance\Resources\MaintenanceMeterReadingResource\Pages\CreateMaintenanceMeterReading;
use App\Filament\Maintenance\Resources\MaintenanceMeterReadingResource\Pages\ListMaintenanceMeterReadings;
use App\Models\MaintenanceMeterReading;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MaintenanceMeterReadingResource extends Resource
{
    protected static ?string $model = MaintenanceMeterReading::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Asset Management';

    protected static ?string $navigationLabel = 'Meter Readings';

    protected static ?string $modelLabel = 'meter reading';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'meter-readings';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Section::make('Meter reading')->schema([
            Select::make('asset_id')->relationship('asset', 'name')->searchable()->preload()->required(),
            TextInput::make('reading')->numeric()->minValue(0)->required(),
            DateTimePicker::make('recorded_at')->default(now())->required(),
            Select::make('source')->options(['manual' => 'Manual', 'inspection' => 'Inspection', 'telematics' => 'Telematics / import'])->default('manual')->required(),
            Textarea::make('notes')->columnSpanFull(),
        ])->columns(4)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('asset.display_name')->label('Asset')->searchable(['asset_tag', 'name']),
            TextColumn::make('reading')->numeric(decimalPlaces: 2)->sortable(),
            TextColumn::make('recorded_at')->dateTime()->sortable(),
            TextColumn::make('recordedBy.name')->label('Recorded by')->placeholder('Public / import'),
            TextColumn::make('source')->badge(),
            TextColumn::make('notes')->wrap()->limit(60),
        ])->defaultSort('recorded_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListMaintenanceMeterReadings::route('/'), 'create' => CreateMaintenanceMeterReading::route('/create')];
    }
}
