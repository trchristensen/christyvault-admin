<?php

namespace App\Filament\Maintenance\Resources;

use App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource\Pages\CreateMaintenanceWorkOrder;
use App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource\Pages\EditMaintenanceWorkOrder;
use App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource\Pages\ListMaintenanceWorkOrders;
use App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource\RelationManagers\LaborEntriesRelationManager;
use App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource\RelationManagers\PartsRelationManager;
use App\Models\MaintenanceAsset;
use App\Models\MaintenanceVendor;
use App\Models\MaintenanceWorkOrder;
use App\Support\MaintenanceOptions;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MaintenanceWorkOrderResource extends Resource
{
    protected static ?string $model = MaintenanceWorkOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance Work';

    protected static ?string $navigationLabel = 'Work Orders';

    protected static ?string $modelLabel = 'work order';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'work-orders';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Work order')->schema([
                TextInput::make('number')->disabled()->dehydrated(false)->placeholder('Assigned after creation'),
                Select::make('asset_id')
                    ->relationship('asset', 'name')
                    ->getOptionLabelFromRecordUsing(fn (MaintenanceAsset $record): string => $record->display_name)
                    ->searchable(['asset_tag', 'name'])
                    ->preload(),
                TextInput::make('title')
                    ->label('Work summary')
                    ->placeholder('BIT inspection and rear suspension repair')
                    ->helperText('A short, searchable description shown in lists, notifications, and the vendor work order.')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('type')
                    ->label('Primary work type')
                    ->options(MaintenanceOptions::workOrderTypes())
                    ->helperText('Choose the main reason for the order. Put any additional inspection, service, or repair tasks in the description or checklist.')
                    ->default('reactive')
                    ->required(),
                Select::make('priority')->options(MaintenanceOptions::priorities())->default('normal')->required(),
                Select::make('status')->options(MaintenanceOptions::workOrderStatuses())->default('approved')->required(),
                Select::make('assigned_to_user_id')->relationship('assignedTo', 'name')->label('Assigned owner / technician')->searchable()->preload(),
                Toggle::make('safety_related')->label('Safety-related'),
                TextInput::make('estimated_hours')->numeric()->minValue(0)->suffix('hours'),
                Textarea::make('description')->columnSpanFull(),
            ])->columns(['default' => 1, 'xl' => 2]),
            Section::make('Schedule and downtime')->schema([
                DateTimePicker::make('scheduled_at'),
                DateTimePicker::make('due_at'),
                DateTimePicker::make('downtime_started_at'),
                DateTimePicker::make('downtime_ended_at'),
            ])->columns(['default' => 1, 'xl' => 2]),
            Section::make('Outside service provider')->description('Optional details included on the vendor printout.')->schema([
                Select::make('maintenance_vendor_id')
                    ->label('Saved service vendor')
                    ->relationship(
                        name: 'maintenanceVendor',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->where('active', true)->orderBy('name'),
                    )
                    ->helperText('Select a saved vendor to fill the contact details below, or type a one-time company manually.')
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
                TextInput::make('service_provider')->label('Company')->maxLength(255),
                TextInput::make('service_contact_name')->label('Contact name')->maxLength(255),
                TextInput::make('service_phone')->label('Phone')->tel()->maxLength(255),
                TextInput::make('vendor_reference')->label('Vendor ticket / reference')->maxLength(255),
                TextInput::make('purchase_order_number')->label('Purchase order')->maxLength(255),
                TextInput::make('authorization_limit')->label('Do not exceed')->numeric()->prefix('$')->minValue(0),
            ])->columns(['default' => 1, 'xl' => 2])->collapsible(),
            Section::make('Procedure checklist')->schema([
                Repeater::make('checklist')->schema([
                    TextInput::make('task')->required()->columnSpanFull(),
                    Toggle::make('completed'),
                    TextInput::make('notes')->columnSpanFull(),
                ])->columns(['default' => 1, 'xl' => 2])->addActionLabel('Add checklist item')->columnSpanFull(),
            ]),
            Section::make('Completion record')->schema([
                Textarea::make('findings')->rows(4),
                Textarea::make('work_performed')->rows(4),
                Textarea::make('completion_notes')->rows(4)->columnSpanFull(),
                FileUpload::make('attachment_paths')->label('Photos and documents')->disk('public')->directory('maintenance/work-orders')->multiple()->columnSpanFull(),
            ])->columns(['default' => 1, 'xl' => 2]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number')->searchable()->sortable(),
            TextColumn::make('asset.display_name')->label('Asset')->searchable(['asset_tag', 'name'])->wrap(),
            TextColumn::make('title')->searchable()->wrap(),
            TextColumn::make('type')
                ->label('Work type')
                ->formatStateUsing(fn ($state) => MaintenanceOptions::workOrderTypes()[$state] ?? $state)
                ->badge()
                ->toggleable(),
            TextColumn::make('priority')->badge()->color(fn ($state) => MaintenanceOptions::colorForPriority($state)),
            TextColumn::make('status')->formatStateUsing(fn ($state) => MaintenanceOptions::workOrderStatuses()[$state] ?? $state)->badge()->color(fn ($state) => match ($state) {
                'completed' => 'success', 'in_progress' => 'info', 'waiting_on_parts', 'on_hold' => 'warning', 'canceled' => 'gray', default => 'primary'
            }),
            TextColumn::make('assignedTo.name')->label('Assigned to')->placeholder('Unassigned')->sortable(),
            TextColumn::make('due_at')->label('Due')->dateTime('M j, Y')->sortable()->color(fn (MaintenanceWorkOrder $record) => $record->due_at?->isPast() && ! in_array($record->status, ['completed', 'canceled']) ? 'danger' : null),
            IconColumn::make('safety_related')->label('Safety')->boolean(),
        ])->defaultSort('due_at')->filters([
            SelectFilter::make('status')->multiple()->options(MaintenanceOptions::workOrderStatuses()),
            SelectFilter::make('priority')->options(MaintenanceOptions::priorities()),
            SelectFilter::make('type')->options(MaintenanceOptions::workOrderTypes()),
            SelectFilter::make('assigned_to_user_id')->relationship('assignedTo', 'name')->label('Technician'),
        ])->recordActions([
            Action::make('print_for_vendor')
                ->label('Print for vendor')
                ->icon('heroicon-o-printer')
                ->url(fn (MaintenanceWorkOrder $record): string => route('maintenance.work-orders.print', $record))
                ->openUrlInNewTab(),
            Action::make('start')->icon('heroicon-o-play')->color('success')->visible(fn (MaintenanceWorkOrder $record) => in_array($record->status, ['approved', 'scheduled', 'on_hold', 'waiting_on_parts']))->action(function (MaintenanceWorkOrder $record): void {
                $record->start(auth()->user());
                Notification::make()->title('Work started')->success()->send();
            }),
            Action::make('pause')->icon('heroicon-o-pause')->color('warning')->visible(fn (MaintenanceWorkOrder $record) => $record->status === 'in_progress')->schema([Select::make('status')->options(['on_hold' => 'On hold', 'waiting_on_parts' => 'Waiting on parts'])->default('on_hold')->required()])->action(fn (MaintenanceWorkOrder $record, array $data) => $record->pause($data['status'])),
            Action::make('complete')->icon('heroicon-o-check-circle')->color('success')->visible(fn (MaintenanceWorkOrder $record) => in_array($record->status, ['in_progress', 'on_hold', 'waiting_on_parts']))->requiresConfirmation()->action(fn (MaintenanceWorkOrder $record) => $record->complete(auth()->user())),
            Action::make('verify')->label('Verify & close')->icon('heroicon-o-shield-check')->color('success')->visible(fn (MaintenanceWorkOrder $record) => $record->status === 'pending_verification' && auth()->user()?->hasRole(['admin', 'super-admin', 'maintenance-manager']))->requiresConfirmation()->action(function (MaintenanceWorkOrder $record): void {
                $record->verify(auth()->user());
                if ($record->asset?->status !== 'retired') {
                    $record->asset?->update(['status' => 'operational']);
                } Notification::make()->title('Work order closed')->success()->send();
            }),
            Action::make('out_of_service')->label('Take out of service')->icon('heroicon-o-no-symbol')->color('danger')->visible(fn (MaintenanceWorkOrder $record) => $record->asset && $record->asset->status !== 'out_of_service')->requiresConfirmation()->action(function (MaintenanceWorkOrder $record): void {
                $record->asset->update(['status' => 'out_of_service']);
                $record->update(['downtime_started_at' => $record->downtime_started_at ?? now()]);
            }),
            EditAction::make(),
        ]);
    }

    public static function getRelations(): array
    {
        return [LaborEntriesRelationManager::class, PartsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceWorkOrders::route('/'),
            'create' => CreateMaintenanceWorkOrder::route('/create'),
            'edit' => EditMaintenanceWorkOrder::route('/{record}/edit'),
        ];
    }
}
