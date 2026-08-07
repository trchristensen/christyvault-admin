<?php

namespace App\Filament\Team\Widgets;

use App\Models\Location;
use App\Models\MaintenanceAsset;
use App\Models\TripPreTripInspection;
use App\Models\TripPreTripInspectionDefect;
use App\Models\User;
use App\Notifications\TripPreTripDefectReported;
use App\Services\Maintenance\VehicleInspectionReportService;
use App\Support\EquipmentCareChecklist;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class EquipmentCareWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.team.widgets.equipment-care-widget';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->canViewTeamDeliverySchedule() ?? false;
    }

    public function submitEquipmentCareAction(): Action
    {
        return Action::make('submitEquipmentCare')
            ->modalHeading('Optional equipment care check')
            ->modalDescription('Use this when you have time to care for equipment. It is optional and does not replace the pre-trip inspection, end-of-day report, or BIT inspection.')
            ->modalSubmitActionLabel('Save care check')
            ->modalWidth('4xl')
            ->stickyModalHeader()
            ->schema([
                Select::make('asset_id')
                    ->label('Equipment')
                    ->options(fn (): array => $this->equipmentOptions())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('completed_tasks', []);
                        $set('tire_readings', []);
                    }),
                TextInput::make('meter_reading')
                    ->label('Odometer / hour meter')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Optional. Enter the current reading if it is easy to obtain.'),
                CheckboxList::make('completed_tasks')
                    ->label('What did you actually complete?')
                    ->helperText('Select only work you performed. It is fine to do one useful task and save it.')
                    ->options(fn (Get $get): array => $this->checklistForAsset($get('asset_id'))
                        ->mapWithKeys(fn (array $item, string $key): array => [$key => new HtmlString(
                            '<span class="block font-semibold text-gray-950 dark:text-white">'.e($item['label']).'</span>'.
                            '<span class="mt-1 block text-sm font-normal leading-5 text-gray-600 dark:text-gray-300"><span class="font-semibold">What to look for:</span> '.e($item['description']).'</span>',
                        )])
                        ->all())
                    ->allowHtml()
                    ->columns(1)
                    ->columnSpanFull(),
                Repeater::make('tire_readings')
                    ->label('Tire-pressure readings')
                    ->helperText('Optional. Record only cold readings. Use the vehicle or tire manufacturer’s target—not a generic PSI.')
                    ->schema([
                        TextInput::make('position')->label('Position')->placeholder('Steer left, drive inner right, trailer axle 1 left')->required()->maxLength(100),
                        TextInput::make('psi')->label('Measured PSI')->numeric()->minValue(0)->maxValue(250)->required(),
                        TextInput::make('target_psi')->label('Target PSI')->numeric()->minValue(0)->maxValue(250),
                    ])
                    ->columns(['default' => 1, 'md' => 3])
                    ->addActionLabel('Add another tire')
                    ->visible(fn (Get $get): bool => in_array('tire_pressures', $get('completed_tasks') ?? [], true))
                    ->columnSpanFull(),
                Textarea::make('care_notes')
                    ->label('Notes about the work completed')
                    ->placeholder('Added approved washer fluid, cleaned both mirrors, no moisture came from the manual tank drains…')
                    ->rows(3)
                    ->columnSpanFull(),
                Checkbox::make('has_issue')
                    ->label('I noticed a problem that should be reviewed')
                    ->helperText('Checking this routes the observation into the existing maintenance issue workflow.')
                    ->live()
                    ->columnSpanFull(),
                TextInput::make('issue_component')
                    ->label('Part or system')
                    ->maxLength(255)
                    ->required(fn (Get $get): bool => (bool) $get('has_issue'))
                    ->visible(fn (Get $get): bool => (bool) $get('has_issue'))
                    ->columnSpanFull(),
                Textarea::make('issue_description')
                    ->label('What did you notice?')
                    ->helperText('Describe the observation. You are not expected to diagnose or repair it.')
                    ->rows(3)
                    ->required(fn (Get $get): bool => (bool) $get('has_issue'))
                    ->visible(fn (Get $get): bool => (bool) $get('has_issue'))
                    ->columnSpanFull(),
                Select::make('operating_concern')
                    ->label('Does the problem appear immediately unsafe?')
                    ->options([
                        TripPreTripInspectionDefect::DRIVER_ASSESSMENT_REVIEW => 'Needs review — no immediate danger noticed',
                        TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP => 'Stop — equipment appears unsafe and should not be used before review',
                    ])
                    ->default(TripPreTripInspectionDefect::DRIVER_ASSESSMENT_REVIEW)
                    ->required(fn (Get $get): bool => (bool) $get('has_issue'))
                    ->visible(fn (Get $get): bool => (bool) $get('has_issue'))
                    ->columnSpanFull(),
                Checkbox::make('certification')
                    ->label('I confirm that this is an accurate record of the optional care work I actually completed and any problems I noticed.')
                    ->accepted()
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                $asset = $this->authorizedAsset($data['asset_id'] ?? null);
                $checklist = EquipmentCareChecklist::items($asset);
                $completedTasks = collect($data['completed_tasks'] ?? [])
                    ->filter(fn ($key): bool => $checklist->has((string) $key))
                    ->map(fn ($key): string => (string) $key)
                    ->unique()
                    ->values();
                $hasIssue = filter_var($data['has_issue'] ?? false, FILTER_VALIDATE_BOOL);
                $issues = $hasIssue && filled($data['issue_component'] ?? null) && filled($data['issue_description'] ?? null)
                    ? collect([[
                        'component' => trim((string) $data['issue_component']),
                        'description' => trim((string) $data['issue_description']),
                    ]])
                    : collect();

                if ($completedTasks->isEmpty() && $issues->isEmpty()) {
                    throw ValidationException::withMessages([
                        'completed_tasks' => 'Select at least one task you completed, or report a problem you noticed.',
                    ]);
                }

                if ($hasIssue && $issues->isEmpty()) {
                    throw ValidationException::withMessages([
                        'issue_description' => 'Describe the problem you noticed.',
                    ]);
                }

                $operatingConcern = $hasIssue
                    ? ($data['operating_concern'] ?? TripPreTripInspectionDefect::DRIVER_ASSESSMENT_REVIEW)
                    : null;
                $user = auth()->user();
                $employee = $user?->employee;
                $submittedAt = now();
                $safeToOperate = $issues->isEmpty();
                $taskSnapshots = $completedTasks->map(fn (string $key): array => [
                    'key' => $key,
                    'label' => $checklist->get($key)['label'],
                ])->all();
                $defectLabels = $issues->mapWithKeys(fn (array $issue, int $index): array => [
                    'care_'.($index + 1) => $issue['component'],
                ])->all();
                $defectNotes = $issues->map(
                    fn (array $issue): string => "{$issue['component']}: {$issue['description']}",
                )->join("\n");
                $service = app(VehicleInspectionReportService::class);

                $inspection = DB::transaction(function () use (
                    $asset,
                    $data,
                    $user,
                    $employee,
                    $submittedAt,
                    $safeToOperate,
                    $taskSnapshots,
                    $defectLabels,
                    $defectNotes,
                    $issues,
                    $operatingConcern,
                    $service,
                ): TripPreTripInspection {
                    $assetColumns = match ($asset->category) {
                        'trailer' => ['trailer_asset_id' => $asset->getKey()],
                        'piggyback_forklift' => ['piggyback_asset_id' => $asset->getKey()],
                        default => ['truck_asset_id' => $asset->getKey()],
                    };
                    $snapshot = $service->assetSnapshot($asset);
                    $inspection = TripPreTripInspection::query()->create([
                        ...$assetColumns,
                        'trip_id' => null,
                        'user_id' => $user?->getKey(),
                        'driver_id' => $employee?->getKey(),
                        'inspection_date' => now('America/Los_Angeles')->toDateString(),
                        'scheduled_date' => null,
                        'checklist_version' => EquipmentCareChecklist::VERSION,
                        'report_type' => TripPreTripInspection::TYPE_EQUIPMENT_CARE,
                        'status' => $safeToOperate ? TripPreTripInspection::STATUS_COMPLETED : TripPreTripInspection::STATUS_DEFECT_REPORTED,
                        'safe_to_operate' => $safeToOperate,
                        'driver_name' => $employee?->name ?? $user?->name ?? 'Unknown employee',
                        'equipment_snapshot' => ['equipment' => $snapshot],
                        'responses' => [
                            'completed_tasks' => $taskSnapshots,
                            'meter_reading' => filled($data['meter_reading'] ?? null) ? (float) $data['meter_reading'] : null,
                            'tire_readings' => collect($data['tire_readings'] ?? [])->map(fn (array $reading): array => [
                                'position' => trim((string) ($reading['position'] ?? '')),
                                'psi' => filled($reading['psi'] ?? null) ? (float) $reading['psi'] : null,
                                'target_psi' => filled($reading['target_psi'] ?? null) ? (float) $reading['target_psi'] : null,
                            ])->filter(fn (array $reading): bool => filled($reading['position']) && $reading['psi'] !== null)->values()->all(),
                            'care_notes' => filled($data['care_notes'] ?? null) ? trim((string) $data['care_notes']) : null,
                        ],
                        'defects' => $defectLabels ?: null,
                        'defect_notes' => $defectNotes ?: null,
                        'certification_text' => 'I confirm that this report accurately records the optional equipment care work I completed and any problems I noticed. This report does not replace a required vehicle inspection.',
                        'completed_at' => $submittedAt,
                    ]);

                    $service->attachAssets($inspection, ['equipment' => $asset]);

                    if ($issues->isNotEmpty()) {
                        $service->createDefects($inspection, $issues->map(fn (array $issue, int $index): array => [
                            'component_key' => 'care_'.($index + 1),
                            'component_label' => $issue['component'],
                            'description' => $issue['description'],
                            'asset' => $asset,
                            'driver_assessment' => $operatingConcern,
                        ])->all());
                    }

                    return $inspection;
                });

                if (! $safeToOperate) {
                    $recipients = User::permission('manage delivery trip dispatch')->get()
                        ->merge(User::role(['admin', 'super-admin', 'maintenance-manager', 'maintenance-technician'])->get())
                        ->reject(fn (User $recipient): bool => $recipient->getKey() === $user?->getKey())
                        ->unique(fn (User $recipient): int => $recipient->getKey())
                        ->values();

                    NotificationFacade::send($recipients, new TripPreTripDefectReported($inspection));
                }

                Notification::make()
                    ->title($safeToOperate ? 'Equipment care recorded' : 'Equipment care and issue recorded')
                    ->color($safeToOperate ? 'success' : 'warning')
                    ->send();
            });
    }

    protected function getViewData(): array
    {
        return [
            'recentChecks' => TripPreTripInspection::query()
                ->where('report_type', TripPreTripInspection::TYPE_EQUIPMENT_CARE)
                ->where('user_id', auth()->id())
                ->with(['assets', 'inspectionDefects'])
                ->latest('completed_at')
                ->limit(4)
                ->get(),
        ];
    }

    protected function equipmentOptions(): array
    {
        return $this->availableAssets()
            ->mapWithKeys(fn (MaintenanceAsset $asset): array => [
                $asset->getKey() => $asset->display_name.($asset->status !== 'operational' ? ' ('.str($asset->status)->headline().')' : ''),
            ])
            ->all();
    }

    protected function checklistForAsset(mixed $assetId)
    {
        $asset = $this->availableAssets()->firstWhere('id', (int) $assetId);

        return EquipmentCareChecklist::items($asset);
    }

    protected function authorizedAsset(mixed $assetId): MaintenanceAsset
    {
        $asset = $this->availableAssets()->firstWhere('id', (int) $assetId);

        if (! $asset) {
            throw ValidationException::withMessages([
                'asset_id' => 'Select equipment available at your assigned plant.',
            ]);
        }

        return $asset;
    }

    protected function availableAssets()
    {
        $user = auth()->user();
        $query = MaintenanceAsset::query()
            ->whereIn('category', EquipmentCareChecklist::SUPPORTED_CATEGORIES)
            ->where('status', '!=', 'retired')
            ->orderBy('category')
            ->orderBy('asset_tag');
        $employeeLocation = mb_strtolower(trim((string) $user?->employee?->christy_location));

        if (filled($employeeLocation)) {
            $plantName = match ($employeeLocation) {
                'colma' => 'Christy Vault - Colma',
                'tulare' => 'Christy Vault - Tulare',
                default => null,
            };
            $locationId = Location::query()
                ->christyVault()
                ->when($plantName, fn ($locationQuery) => $locationQuery->where('name', $plantName))
                ->when(! $plantName, fn ($locationQuery) => $locationQuery->whereRaw('LOWER(name) LIKE ?', ['%'.$employeeLocation.'%']))
                ->value('id');

            $query->where('location_id', $locationId ?: -1);
        } elseif (! $user?->hasAnyRole(['admin', 'super-admin'])) {
            $query->whereRaw('1 = 0');
        }

        return $query->get();
    }
}
