<?php

namespace App\Filament\Maintenance\Resources;

use App\Filament\Maintenance\Resources\MaintenanceFleetPlanResource\Pages\CreateMaintenanceFleetPlan;
use App\Filament\Maintenance\Resources\MaintenanceFleetPlanResource\Pages\EditMaintenanceFleetPlan;
use App\Filament\Maintenance\Resources\MaintenanceFleetPlanResource\Pages\ListMaintenanceFleetPlans;
use App\Filament\Maintenance\Resources\MaintenanceFleetPlanResource\RelationManagers\AssetsRelationManager;
use App\Filament\Maintenance\Resources\MaintenanceFleetPlanResource\RelationManagers\ServiceRunsRelationManager;
use App\Models\MaintenanceFleetPlan;
use App\Models\MaintenanceVendor;
use App\Support\MaintenanceOptions;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceFleetPlanResource extends Resource
{
    protected static ?string $model = MaintenanceFleetPlan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Preventive Maintenance';

    protected static ?string $navigationLabel = 'Fleet PM Plans';

    protected static ?string $modelLabel = 'fleet PM plan';

    protected static ?string $slug = 'fleet-preventive-maintenance';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Fleet service plan')->schema([
                TextInput::make('name')->required()->maxLength(255),
                Select::make('default_assignee_id')->relationship('defaultAssignee', 'name')->label('Internal coordinator')->searchable()->preload(),
                Select::make('priority')->options(MaintenanceOptions::priorities())->default('normal')->required(),
                Toggle::make('active')->default(true),
                Textarea::make('description')->helperText('Explain what service to request and that every included asset is serviced together.')->columnSpanFull(),
            ])->columns(['default' => 1, 'xl' => 2]),
            Section::make('Automatically included assets')->description('Matching assets join this plan automatically. Use the Fleet assets section after saving to exclude low-use units.')->schema([
                Select::make('location_id')
                    ->label('Home plant')
                    ->relationship(
                        name: 'location',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->christyVault()->orderBy('name'),
                    )
                    ->searchable()
                    ->preload()
                    ->columnSpanFull()
                    ->required(),
                TextInput::make('manufacturer')->placeholder('Hyster')->required()->maxLength(255),
                Select::make('asset_category')->label('Category')->options(MaintenanceOptions::assetCategories())->searchable()->required(),
                Select::make('meter_type')->options(MaintenanceOptions::meterTypes())->default('hours')->required(),
                TextInput::make('meter_interval')->label('Service every')->numeric()->minValue(0.01)->suffix('meter units')->default(250)->required(),
            ])->columns(['default' => 1, 'xl' => 2]),
            Section::make('Outside service provider')->schema([
                Select::make('maintenance_vendor_id')
                    ->label('Saved service vendor')
                    ->relationship(
                        name: 'maintenanceVendor',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->where('active', true)->orderBy('name'),
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->columnSpanFull()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        $vendor = $state ? MaintenanceVendor::find($state) : null;

                        if ($vendor) {
                            foreach ($vendor->snapshot() as $field => $value) {
                                $set($field, $value);
                            }
                        }
                    }),
                TextInput::make('service_provider')->placeholder('Papé'),
                TextInput::make('service_contact_name')->label('Contact name'),
                TextInput::make('service_phone')->label('Phone')->tel(),
            ])->columns(['default' => 1, 'xl' => 2]),
            Section::make('Standard procedure')->schema([
                Repeater::make('checklist')->schema([
                    TextInput::make('task')->required(),
                ])->addActionLabel('Add procedure step')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->wrap(),
            TextColumn::make('location.name')->label('Plant')->sortable(),
            TextColumn::make('manufacturer')->searchable(),
            TextColumn::make('asset_category')->label('Category')->formatStateUsing(fn ($state) => MaintenanceOptions::assetCategories()[$state] ?? $state)->badge(),
            TextColumn::make('meter_interval')->label('Interval')->formatStateUsing(fn ($state, MaintenanceFleetPlan $record) => number_format((float) $state, 1).' '.$record->meter_type),
            TextColumn::make('included_assets')->label('Included assets')->state(fn (MaintenanceFleetPlan $record): int => $record->members()->where('included', true)->where('matches_filter', true)->count()),
            TextColumn::make('next_trigger')->label('Next trigger')->state(function (MaintenanceFleetPlan $record): string {
                $member = $record->members()->with('asset')->where('included', true)->where('matches_filter', true)->whereNotNull('next_due_meter')->get()
                    ->sortBy(fn ($member) => (float) $member->next_due_meter - (float) ($member->asset?->current_meter ?? 0))->first();

                return $member?->asset
                    ? $member->asset->asset_tag.' at '.number_format((float) $member->next_due_meter, 1)
                    : 'Needs baseline';
            }),
            IconColumn::make('active')->boolean(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [AssetsRelationManager::class, ServiceRunsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceFleetPlans::route('/'),
            'create' => CreateMaintenanceFleetPlan::route('/create'),
            'edit' => EditMaintenanceFleetPlan::route('/{record}/edit'),
        ];
    }
}
