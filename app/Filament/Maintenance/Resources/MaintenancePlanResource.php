<?php

namespace App\Filament\Maintenance\Resources;

use App\Filament\Maintenance\Resources\MaintenancePlanResource\Pages\CreateMaintenancePlan;
use App\Filament\Maintenance\Resources\MaintenancePlanResource\Pages\EditMaintenancePlan;
use App\Filament\Maintenance\Resources\MaintenancePlanResource\Pages\ListMaintenancePlans;
use App\Models\MaintenanceAsset;
use App\Models\MaintenancePlan;
use App\Services\Maintenance\MaintenancePlanScheduler;
use App\Support\MaintenanceOptions;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MaintenancePlanResource extends Resource
{
    protected static ?string $model = MaintenancePlan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Preventive Maintenance';

    protected static ?string $navigationLabel = 'PM Plans';

    protected static ?string $modelLabel = 'preventive maintenance plan';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'preventive-maintenance';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Preventive maintenance plan')->schema([
                Select::make('asset_id')
                    ->relationship('asset', 'name')
                    ->getOptionLabelFromRecordUsing(fn (MaintenanceAsset $record): string => $record->display_name)
                    ->searchable(['asset_tag', 'name'])
                    ->preload()
                    ->required(),
                TextInput::make('name')->required()->maxLength(255),
                Select::make('default_assignee_id')->relationship('defaultAssignee', 'name')->label('Default technician')->searchable()->preload(),
                Select::make('priority')->options(MaintenanceOptions::priorities())->default('normal')->required(),
                Textarea::make('description')->columnSpanFull(),
                Toggle::make('active')->default(true),
            ])->columns(['default' => 1, 'xl' => 2]),
            Section::make('Trigger')->schema([
                Select::make('trigger_type')->options(MaintenanceOptions::triggerTypes())->default('calendar')->required()->live(),
                TextInput::make('interval_value')->label('Every')->numeric()->integer()->minValue(1)->default(1)->visible(fn (Get $get) => $get('trigger_type') === 'calendar')->required(fn (Get $get) => $get('trigger_type') === 'calendar'),
                Select::make('interval_unit')->options(MaintenanceOptions::intervalUnits())->default('months')->visible(fn (Get $get) => $get('trigger_type') === 'calendar')->required(fn (Get $get) => $get('trigger_type') === 'calendar'),
                DatePicker::make('next_due_date')->visible(fn (Get $get) => $get('trigger_type') === 'calendar')->required(fn (Get $get) => $get('trigger_type') === 'calendar'),
                TextInput::make('meter_interval')->label('Every meter units')->numeric()->minValue(0.01)->visible(fn (Get $get) => $get('trigger_type') === 'meter')->required(fn (Get $get) => $get('trigger_type') === 'meter'),
                TextInput::make('next_due_meter')->label('Next due reading')->numeric()->minValue(0)->visible(fn (Get $get) => $get('trigger_type') === 'meter')->required(fn (Get $get) => $get('trigger_type') === 'meter'),
                TextInput::make('lead_days')->label('Generate this many days early')->numeric()->integer()->minValue(0)->default(7)->visible(fn (Get $get) => $get('trigger_type') === 'calendar'),
            ])->columns(['default' => 1, 'xl' => 2]),
            Section::make('Standard procedure')->schema([
                Repeater::make('checklist')->schema([TextInput::make('task')->required()])->addActionLabel('Add procedure step')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('asset.display_name')->label('Asset')->searchable(['asset_tag', 'name'])->wrap(),
            TextColumn::make('name')->searchable()->wrap(),
            TextColumn::make('trigger_type')->badge(),
            TextColumn::make('schedule')->label('Schedule')->state(fn (MaintenancePlan $record) => $record->trigger_type === 'meter' ? 'Every '.number_format((float) $record->meter_interval, 1).' units' : "Every {$record->interval_value} {$record->interval_unit}"),
            TextColumn::make('next_due')->label('Next due')->state(fn (MaintenancePlan $record) => $record->trigger_type === 'meter' ? number_format((float) $record->next_due_meter, 1) : $record->next_due_date?->format('M j, Y')),
            TextColumn::make('defaultAssignee.name')->label('Technician')->placeholder('Unassigned'),
            IconColumn::make('active')->boolean(),
        ])->filters([SelectFilter::make('trigger_type')->options(MaintenanceOptions::triggerTypes())])->recordActions([
            Action::make('generate')->label('Generate now')->icon('heroicon-o-bolt')->requiresConfirmation()->action(function (MaintenancePlan $record): void {
                $workOrder = app(MaintenancePlanScheduler::class)->generate($record);
                Notification::make()->title($workOrder ? "Created {$workOrder->number}" : 'A work order is already open or the plan is not due')->color($workOrder ? 'success' : 'warning')->send();
            }),
            EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenancePlans::route('/'),
            'create' => CreateMaintenancePlan::route('/create'),
            'edit' => EditMaintenancePlan::route('/{record}/edit'),
        ];
    }
}
