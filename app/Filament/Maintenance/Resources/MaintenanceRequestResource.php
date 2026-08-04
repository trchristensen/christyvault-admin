<?php

namespace App\Filament\Maintenance\Resources;

use App\Filament\Maintenance\Resources\MaintenanceRequestResource\Pages\CreateMaintenanceRequest;
use App\Filament\Maintenance\Resources\MaintenanceRequestResource\Pages\EditMaintenanceRequest;
use App\Filament\Maintenance\Resources\MaintenanceRequestResource\Pages\ListMaintenanceRequests;
use App\Models\MaintenanceAsset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\Maintenance\MaintenanceRequestConverter;
use App\Support\MaintenanceOptions;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
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
use Illuminate\Database\Eloquent\Builder;

class MaintenanceRequestResource extends Resource
{
    protected static ?string $model = MaintenanceRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static string|\UnitEnum|null $navigationGroup = 'Maintenance Work';

    protected static ?string $navigationLabel = 'Requests';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'requests';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request')->schema([
                Select::make('asset_id')
                    ->relationship('asset', 'name')
                    ->getOptionLabelFromRecordUsing(fn (MaintenanceAsset $record): string => $record->display_name)
                    ->searchable(['asset_tag', 'name'])
                    ->preload(),
                Select::make('location_id')
                    ->label('Plant')
                    ->relationship(
                        name: 'location',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->christyVault()->orderBy('name'),
                    )
                    ->searchable()
                    ->preload(),
                TextInput::make('requester_name')->maxLength(255),
                TextInput::make('requester_contact')->maxLength(255),
                TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                Select::make('priority')->options(MaintenanceOptions::priorities())->default('normal')->required(),
                Toggle::make('safety_related')->label('Safety-related issue'),
                Textarea::make('description')->required()->columnSpanFull(),
                FileUpload::make('photo_paths')->label('Photos')->disk('public')->directory('maintenance/requests')->image()->multiple()->columnSpanFull(),
            ])->columns(['default' => 1, 'xl' => 2]),
            Section::make('Triage')->schema([
                Select::make('status')->options(MaintenanceOptions::requestStatuses())->default('new')->required(),
                Textarea::make('triage_notes')->columnSpanFull(),
            ])->columns(['default' => 1, 'xl' => 2]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('submitted_at')->label('Submitted')->dateTime('M j, Y g:i A')->sortable(),
            TextColumn::make('asset.display_name')->label('Asset')->searchable(['asset_tag', 'name'])->wrap(),
            TextColumn::make('title')->searchable()->wrap(),
            TextColumn::make('requester_name')->label('Requested by')->searchable(),
            TextColumn::make('priority')->badge()->color(fn ($state) => MaintenanceOptions::colorForPriority($state)),
            IconColumn::make('safety_related')->label('Safety')->boolean(),
            TextColumn::make('status')->badge(),
        ])->defaultSort('submitted_at', 'desc')->filters([
            SelectFilter::make('status')->options(MaintenanceOptions::requestStatuses()),
            SelectFilter::make('priority')->options(MaintenanceOptions::priorities()),
        ])->recordActions([
            Action::make('convert')
                ->label('Create work order')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('success')
                ->visible(fn (MaintenanceRequest $record) => $record->workOrder === null && $record->status === 'new')
                ->schema([
                    Select::make('assigned_to_user_id')->label('Assign to')->options(User::query()->orderBy('name')->pluck('name', 'id'))->searchable(),
                    DateTimePicker::make('scheduled_at'),
                    DateTimePicker::make('due_at'),
                ])
                ->action(function (MaintenanceRequest $record, array $data): void {
                    $workOrder = app(MaintenanceRequestConverter::class)->convert($record, auth()->user(), array_filter($data));
                    Notification::make()->title("Work order {$workOrder->number} created")->success()->send();
                    redirect(MaintenanceWorkOrderResource::getUrl('edit', ['record' => $workOrder]));
                }),
            Action::make('reject')->color('danger')->requiresConfirmation()->visible(fn (MaintenanceRequest $record) => $record->status === 'new')->action(fn (MaintenanceRequest $record) => $record->update(['status' => 'rejected', 'triaged_by_user_id' => auth()->id(), 'triaged_at' => now()])),
            EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceRequests::route('/'),
            'create' => CreateMaintenanceRequest::route('/create'),
            'edit' => EditMaintenanceRequest::route('/{record}/edit'),
        ];
    }
}
