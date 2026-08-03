<?php

namespace App\Filament\Maintenance\Resources;

use App\Filament\Maintenance\Resources\MaintenanceVendorResource\Pages\CreateMaintenanceVendor;
use App\Filament\Maintenance\Resources\MaintenanceVendorResource\Pages\EditMaintenanceVendor;
use App\Filament\Maintenance\Resources\MaintenanceVendorResource\Pages\ListMaintenanceVendors;
use App\Models\MaintenanceVendor;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MaintenanceVendorResource extends Resource
{
    protected static ?string $model = MaintenanceVendor::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance Setup';

    protected static ?string $navigationLabel = 'Service Vendors';

    protected static ?string $modelLabel = 'service vendor';

    protected static ?string $slug = 'service-vendors';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Service vendor')->schema([
                TextInput::make('name')->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('contact_person')->label('Primary contact')->maxLength(255),
                TextInput::make('phone')->tel()->maxLength(255),
                TextInput::make('email')->email()->maxLength(255),
                Toggle::make('active')->default(true),
                Textarea::make('address')->rows(3)->columnSpan(2),
                Textarea::make('services_provided')->label('Services provided')->placeholder('Forklift maintenance, Service B, emergency repairs')->rows(3)->columnSpan(2),
                Textarea::make('notes')->rows(3)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('contact_person')->label('Contact')->searchable()->placeholder('—'),
            TextColumn::make('phone')->searchable()->placeholder('—'),
            TextColumn::make('email')->searchable()->placeholder('—'),
            TextColumn::make('services_provided')->label('Services')->wrap()->limit(70),
            TextColumn::make('work_orders_count')->counts('workOrders')->label('Work orders'),
            IconColumn::make('active')->boolean(),
        ])->recordActions([
            EditAction::make(),
        ])->toolbarActions([
            BulkActionGroup::make([DeleteBulkAction::make()]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceVendors::route('/'),
            'create' => CreateMaintenanceVendor::route('/create'),
            'edit' => EditMaintenanceVendor::route('/{record}/edit'),
        ];
    }
}
