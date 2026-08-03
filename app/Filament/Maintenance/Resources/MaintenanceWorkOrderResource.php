<?php

namespace App\Filament\Maintenance\Resources;

use App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource\Pages\CreateMaintenanceWorkOrder;
use App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource\Pages\EditMaintenanceWorkOrder;
use App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource\Pages\ListMaintenanceWorkOrders;
use App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource\RelationManagers\LaborEntriesRelationManager;
use App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource\RelationManagers\PartsRelationManager;
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
                Select::make('asset_id')->relationship('asset', 'name')->searchable()->preload(),
                TextInput::make('title')->required()->maxLength(255)->columnSpan(2),
                Select::make('type')->options(MaintenanceOptions::workOrderTypes())->default('reactive')->required(),
                Select::make('priority')->options(MaintenanceOptions::priorities())->default('normal')->required(),
                Select::make('status')->options(MaintenanceOptions::workOrderStatuses())->default('approved')->required(),
                Select::make('assigned_to_user_id')->relationship('assignedTo', 'name')->label('Assigned technician')->searchable()->preload(),
                Toggle::make('safety_related')->label('Safety-related'),
                TextInput::make('estimated_hours')->numeric()->minValue(0)->suffix('hours'),
                Textarea::make('description')->columnSpanFull(),
            ])->columns(4),
            Section::make('Schedule and downtime')->schema([
                DateTimePicker::make('scheduled_at'),
                DateTimePicker::make('due_at'),
                DateTimePicker::make('downtime_started_at'),
                DateTimePicker::make('downtime_ended_at'),
            ])->columns(4),
            Section::make('Procedure checklist')->schema([
                Repeater::make('checklist')->schema([
                    TextInput::make('task')->required()->columnSpan(2),
                    Toggle::make('completed'),
                    TextInput::make('notes'),
                ])->columns(4)->addActionLabel('Add checklist item')->columnSpanFull(),
            ]),
            Section::make('Completion record')->schema([
                Textarea::make('findings')->rows(4),
                Textarea::make('work_performed')->rows(4),
                Textarea::make('completion_notes')->rows(4)->columnSpanFull(),
                FileUpload::make('attachment_paths')->label('Photos and documents')->disk('public')->directory('maintenance/work-orders')->multiple()->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number')->searchable()->sortable(),
            TextColumn::make('asset.display_name')->label('Asset')->searchable(['asset_tag', 'name'])->wrap(),
            TextColumn::make('title')->searchable()->wrap(),
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
