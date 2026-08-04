<?php

namespace App\Filament\Maintenance\Resources;

use App\Filament\Maintenance\Resources\MaintenanceAssetResource\Pages\CreateMaintenanceAsset;
use App\Filament\Maintenance\Resources\MaintenanceAssetResource\Pages\EditMaintenanceAsset;
use App\Filament\Maintenance\Resources\MaintenanceAssetResource\Pages\ListMaintenanceAssets;
use App\Filament\Maintenance\Resources\MaintenanceAssetResource\RelationManagers\MeterReadingsRelationManager;
use App\Filament\Maintenance\Resources\MaintenanceAssetResource\RelationManagers\PlansRelationManager;
use App\Filament\Maintenance\Resources\MaintenanceAssetResource\RelationManagers\WorkOrdersRelationManager;
use App\Models\MaintenanceAsset;
use App\Support\MaintenanceOptions;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceAssetResource extends Resource
{
    protected static ?string $model = MaintenanceAsset::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Asset Management';

    protected static ?string $navigationLabel = 'Assets';

    protected static ?string $modelLabel = 'asset';

    protected static ?string $slug = 'assets';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Asset identity')->schema([
                TextInput::make('asset_tag')->label('Asset number / tag')->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('name')->required()->maxLength(255),
                Select::make('category')->options(MaintenanceOptions::assetCategories())->required()->searchable(),
                Select::make('parent_id')
                    ->label('Parent asset')
                    ->relationship('parent', 'name')
                    ->getOptionLabelFromRecordUsing(fn (MaintenanceAsset $record): string => $record->display_name)
                    ->searchable(['asset_tag', 'name'])
                    ->preload(),
                Select::make('location_id')
                    ->label('Home plant')
                    ->relationship(
                        name: 'location',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->christyVault()->orderBy('name'),
                    )
                    ->helperText('The Christy Vault plant where this asset is normally based.')
                    ->searchable()
                    ->preload(),
                Select::make('criticality')->options(MaintenanceOptions::criticalities())->default('medium')->required(),
                Select::make('status')->options(MaintenanceOptions::assetStatuses())->default('operational')->required(),
                Textarea::make('description')->columnSpanFull(),
            ])->columns(['default' => 1, 'xl' => 2]),
            Section::make('Equipment details')->schema([
                TextInput::make('manufacturer')->maxLength(255),
                TextInput::make('model')->maxLength(255),
                TextInput::make('serial_number')->maxLength(255),
                TextInput::make('license_plate')->label('License plate')->maxLength(255),
                TextInput::make('year')->numeric()->integer()->minValue(1900)->maxValue((int) date('Y') + 1),
                DatePicker::make('acquired_on'),
                DatePicker::make('warranty_expires_on'),
                DatePicker::make('registration_expires_on')->label('Registration expires'),
                Select::make('meter_type')->options(MaintenanceOptions::meterTypes())->nullable(),
                TextInput::make('current_meter')->numeric()->minValue(0)->helperText('Normally updated through meter readings.'),
                FileUpload::make('photo_path')->label('Photo')->disk('public')->directory('maintenance/assets')->image(),
                FileUpload::make('manual_path')->label('Manual / document')->disk('public')->directory('maintenance/manuals'),
                Textarea::make('notes')->columnSpanFull(),
            ])->columns(['default' => 1, 'xl' => 2]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('asset_tag')->label('Asset')->searchable()->sortable(),
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('category')->formatStateUsing(fn ($state) => MaintenanceOptions::assetCategories()[$state] ?? $state)->badge(),
            TextColumn::make('location.name')->label('Location')->searchable()->sortable(),
            TextColumn::make('status')->formatStateUsing(fn ($state) => MaintenanceOptions::assetStatuses()[$state] ?? $state)->badge()->color(fn ($state) => MaintenanceOptions::colorForAssetStatus($state)),
            TextColumn::make('criticality')->badge()->color(fn ($state) => in_array($state, ['critical', 'high']) ? 'danger' : ($state === 'medium' ? 'warning' : 'gray')),
            TextColumn::make('current_meter')->label('Meter')->formatStateUsing(fn ($state, MaintenanceAsset $record) => filled($state) ? number_format((float) $state, 1).' '.($record->meter_type ?? '') : '—'),
            TextColumn::make('license_plate')->label('License')->searchable()->toggleable(),
            TextColumn::make('registration_expires_on')
                ->label('Registration')
                ->date('M j, Y')
                ->color(fn (MaintenanceAsset $record): ?string => $record->registration_expires_on?->isPast() ? 'danger' : null)
                ->sortable()
                ->toggleable(),
            TextColumn::make('work_orders_count')->counts('workOrders')->label('Work orders'),
        ])->filters([
            SelectFilter::make('category')->options(MaintenanceOptions::assetCategories()),
            SelectFilter::make('status')->options(MaintenanceOptions::assetStatuses()),
            SelectFilter::make('criticality')->options(MaintenanceOptions::criticalities()),
        ])->recordActions([
            Action::make('qr')->label('QR page')->icon('heroicon-o-qr-code')->url(fn (MaintenanceAsset $record) => $record->qr_url)->openUrlInNewTab(),
            Action::make('label')->label('Print label')->icon('heroicon-o-printer')->url(fn (MaintenanceAsset $record) => route('maintenance.assets.label', $record->qr_token))->openUrlInNewTab(),
            EditAction::make(),
        ])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [WorkOrdersRelationManager::class, PlansRelationManager::class, MeterReadingsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceAssets::route('/'),
            'create' => CreateMaintenanceAsset::route('/create'),
            'edit' => EditMaintenanceAsset::route('/{record}/edit'),
        ];
    }
}
