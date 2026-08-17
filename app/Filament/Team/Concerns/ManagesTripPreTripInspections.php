<?php

namespace App\Filament\Team\Concerns;

use App\Models\Location;
use App\Models\MaintenanceAsset;
use App\Models\Trip;
use App\Models\TripPreTripInspection;
use App\Models\TripPreTripInspectionDefect;
use App\Models\VehicleConfiguration;
use App\Notifications\TripPreTripDefectReported;
use App\Services\Maintenance\VehicleInspectionNotificationRecipients;
use App\Services\Maintenance\VehicleInspectionReportService;
use App\Support\TripDailyVehicleReportChecklist;
use App\Support\TripPreTripChecklist;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Validation\ValidationException;

trait ManagesTripPreTripInspections
{
    public function completeTripPreTripInspectionAction(): Action
    {
        return Action::make('completeTripPreTripInspection')
            ->modalHeading(function (Action $action): string {
                $trip = $this->tripForPreTripInspection((int) ($action->getArguments()['trip'] ?? 0));

                return "Pre-trip check — {$trip->trip_number}";
            })
            ->modalDescription(function (Action $action): string {
                $trip = $this->tripForPreTripInspection((int) ($action->getArguments()['trip'] ?? 0));

                return collect([
                    $trip->vehicleConfiguration?->name ?? 'Vehicle configuration not selected',
                    $trip->scheduled_date?->format('l, M j, Y'),
                    'Complete every applicable item. Report any unsafe condition before driving.',
                ])->filter()->join(' · ');
            })
            ->modalSubmitActionLabel('Submit inspection')
            ->modalWidth('4xl')
            ->extraModalWindowAttributes(['class' => 'team-pre-trip-inspection-modal'])
            ->stickyModalHeader()
            ->steps(function (Action $action): array {
                $trip = $this->tripForPreTripInspection((int) ($action->getArguments()['trip'] ?? 0));

                return $this->preTripInspectionSteps($trip);
            })
            ->fillForm(function (Action $action): array {
                $trip = $this->tripForPreTripInspection((int) ($action->getArguments()['trip'] ?? 0));
                $lastInspection = TripPreTripInspection::query()
                    ->where('driver_id', $trip->driver_id)
                    ->latest('completed_at')
                    ->first();

                return [
                    'truck_asset_id' => $lastInspection?->truck_asset_id,
                    'trailer_asset_id' => TripPreTripChecklist::usesTrailer($trip->vehicleConfiguration)
                        ? $lastInspection?->trailer_asset_id
                        : null,
                    'piggyback_asset_id' => TripPreTripChecklist::usesPiggyback($trip->vehicleConfiguration)
                        ? $lastInspection?->piggyback_asset_id
                        : null,
                    'responses' => [],
                    'defect_notes' => null,
                    'operating_concern' => null,
                    'certification' => false,
                ];
            })
            ->action(function (Action $action, array $data): void {
                $trip = $this->tripForPreTripInspection((int) ($action->getArguments()['trip'] ?? 0));
                $configuration = $trip->vehicleConfiguration;
                $items = TripPreTripChecklist::items($configuration);
                $responses = collect($data['responses'] ?? []);
                $normalizedResponses = [];
                $defects = [];

                foreach ($items as $key => $item) {
                    $response = $responses->get($key);
                    $allowed = [TripPreTripChecklist::RESPONSE_OK, TripPreTripChecklist::RESPONSE_DEFECT];

                    if ($item['allow_not_applicable'] ?? false) {
                        $allowed[] = TripPreTripChecklist::RESPONSE_NOT_APPLICABLE;
                    }

                    if (! in_array($response, $allowed, true)) {
                        throw ValidationException::withMessages([
                            "responses.{$key}" => 'Complete this inspection item before submitting.',
                        ]);
                    }

                    $normalizedResponses[$key] = $response;

                    if ($response === TripPreTripChecklist::RESPONSE_DEFECT) {
                        $defects[$key] = $item['label'];
                    }
                }

                if ($defects !== [] && blank($data['defect_notes'] ?? null)) {
                    throw ValidationException::withMessages([
                        'defect_notes' => 'Describe what you noticed and where you noticed it.',
                    ]);
                }

                $operatingConcern = $defects === [] ? null : ($data['operating_concern'] ?? null);

                if ($defects !== [] && ! in_array($operatingConcern, [
                    TripPreTripInspectionDefect::DRIVER_ASSESSMENT_REVIEW,
                    TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP,
                ], true)) {
                    throw ValidationException::withMessages([
                        'operating_concern' => 'Tell us whether this needs review or the equipment feels immediately unsafe.',
                    ]);
                }

                if (! filter_var($data['certification'] ?? false, FILTER_VALIDATE_BOOL)) {
                    throw ValidationException::withMessages([
                        'certification' => 'You must certify this inspection before submitting.',
                    ]);
                }

                $truck = $this->inspectionAsset(
                    $data['truck_asset_id'] ?? null,
                    $this->truckAssetCategories($configuration),
                    'truck_asset_id',
                    $trip,
                );
                $trailer = TripPreTripChecklist::usesTrailer($configuration)
                    ? $this->inspectionAsset($data['trailer_asset_id'] ?? null, ['trailer'], 'trailer_asset_id', $trip)
                    : null;
                $piggyback = TripPreTripChecklist::usesPiggyback($configuration)
                    ? $this->inspectionAsset($data['piggyback_asset_id'] ?? null, ['piggyback_forklift'], 'piggyback_asset_id', $trip)
                    : null;
                $safeToOperate = $defects === [];
                $user = auth()->user();
                $driverName = $trip->driver?->name ?? $user?->name ?? 'Unknown driver';

                $inspection = DB::transaction(function () use (
                    $trip,
                    $user,
                    $configuration,
                    $truck,
                    $trailer,
                    $piggyback,
                    $normalizedResponses,
                    $defects,
                    $data,
                    $safeToOperate,
                    $driverName,
                    $operatingConcern,
                ): TripPreTripInspection {
                    $inspection = TripPreTripInspection::query()->create([
                        'trip_id' => $trip->getKey(),
                        'user_id' => $user?->getKey(),
                        'driver_id' => $trip->driver_id,
                        'vehicle_configuration_id' => $configuration?->getKey(),
                        'truck_asset_id' => $truck->getKey(),
                        'trailer_asset_id' => $trailer?->getKey(),
                        'piggyback_asset_id' => $piggyback?->getKey(),
                        'inspection_date' => now('America/Los_Angeles')->toDateString(),
                        'scheduled_date' => $trip->scheduled_date?->toDateString(),
                        'checklist_version' => TripPreTripChecklist::VERSION,
                        'report_type' => TripPreTripInspection::TYPE_PRE_TRIP,
                        'prior_report_reviewed_at' => ($normalizedResponses['prior_report'] ?? null) === TripPreTripChecklist::RESPONSE_OK
                            ? now()
                            : null,
                        'status' => $safeToOperate
                            ? TripPreTripInspection::STATUS_COMPLETED
                            : TripPreTripInspection::STATUS_DEFECT_REPORTED,
                        'safe_to_operate' => $safeToOperate,
                        'driver_name' => $driverName,
                        'vehicle_configuration_snapshot' => $configuration ? [
                            'id' => $configuration->getKey(),
                            'code' => $configuration->code,
                            'name' => $configuration->name,
                            'configuration_type' => $configuration->configuration_type,
                            'piggyback_forklift_onboard' => $configuration->piggyback_forklift_onboard,
                        ] : null,
                        'equipment_snapshot' => [
                            'truck' => $this->inspectionAssetSnapshot($truck),
                            'trailer' => $this->inspectionAssetSnapshot($trailer),
                            'piggyback' => $this->inspectionAssetSnapshot($piggyback),
                        ],
                        'responses' => $normalizedResponses,
                        'defects' => $defects === [] ? null : $defects,
                        'defect_notes' => $data['defect_notes'] ?? null,
                        'certification_text' => TripPreTripChecklist::certificationText($safeToOperate),
                        'completed_at' => now(),
                    ]);

                    $service = app(VehicleInspectionReportService::class);
                    $assets = ['truck' => $truck, 'trailer' => $trailer, 'piggyback' => $piggyback];
                    $service->attachAssets($inspection, $assets);

                    if ($defects !== []) {
                        $service->createDefects($inspection, collect($defects)
                            ->map(fn (string $label, string $key): array => [
                                'component_key' => $key,
                                'component_label' => $label,
                                'description' => (string) $data['defect_notes'],
                                'asset' => $assets[$this->inspectionAssetRoleForComponent($key)] ?? $truck,
                                'driver_assessment' => $operatingConcern,
                            ])
                            ->values()
                            ->all());
                    }

                    return $inspection;
                });

                if (! $safeToOperate) {
                    $recipients = app(VehicleInspectionNotificationRecipients::class)
                        ->forInspection($inspection, $user?->getKey());

                    NotificationFacade::send($recipients, new TripPreTripDefectReported($inspection));
                }

                $this->refreshTripPreTripInspectionView();

                Notification::make()
                    ->title($safeToOperate ? 'Pre-trip inspection recorded' : 'Defects reported')
                    ->body($safeToOperate
                        ? "{$trip->trip_number} was recorded as safe to operate."
                        : ($operatingConcern === TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP
                            ? 'The driver reported an immediate safety concern. Do not operate the affected equipment until it is cleared.'
                            : 'The driver reported a concern for manager review. The affected equipment was marked restricted, not automatically taken out of service.'))
                    ->color($safeToOperate ? 'success' : 'danger')
                    ->send();
            });
    }

    public function viewTripPreTripInspectionAction(): Action
    {
        return Action::make('viewTripPreTripInspection')
            ->registerModalActions([
                $this->reviewTripInspectionIssueAction(),
            ])
            ->modalHeading(function (Action $action): string {
                $inspection = $this->tripPreTripInspectionForView((int) ($action->getArguments()['inspection'] ?? 0));

                return "{$inspection->report_type_label} — {$inspection->trip->trip_number}";
            })
            ->modalContent(function (Action $action) {
                $inspection = $this->tripPreTripInspectionForView((int) ($action->getArguments()['inspection'] ?? 0));

                return view('filament.team.pre-trip-inspection-summary', [
                    'inspection' => $inspection,
                    'sections' => TripPreTripChecklist::sections($inspection->vehicleConfiguration),
                ]);
            })
            ->modalAutofocus(false)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('4xl')
            ->extraModalWindowAttributes(['class' => 'team-pre-trip-inspection-modal'])
            ->stickyModalHeader();
    }

    public function reuseSameDayTripPreTripInspectionAction(): Action
    {
        return Action::make('reuseSameDayTripPreTripInspection')
            ->modalHeading('Use today’s inspection')
            ->modalDescription(function (Action $action): string {
                $trip = $this->tripForPreTripInspection((int) ($action->getArguments()['trip'] ?? 0));
                $source = $this->sameDayInspectionForReuse($trip, (int) ($action->getArguments()['inspection'] ?? 0));

                return 'Confirm you are using the same equipment: '.collect($source->equipment_snapshot)
                    ->filter()
                    ->map(fn (array $asset): string => '#'.data_get($asset, 'asset_tag').' '.data_get($asset, 'name'))
                    ->join(' · ');
            })
            ->modalSubmitActionLabel('Confirm for this trip')
            ->modalWidth('lg')
            ->extraModalWindowAttributes(['class' => 'team-pre-trip-inspection-modal'])
            ->schema([
                Checkbox::make('same_equipment_confirmation')
                    ->label('I am using this exact equipment, today’s inspection is still accurate, and I have not noticed a new issue.')
                    ->helperText('If the equipment changed or you noticed anything new, close this and start a new inspection instead.')
                    ->accepted()
                    ->required(),
            ])
            ->action(function (Action $action, array $data): void {
                if (! filter_var($data['same_equipment_confirmation'] ?? false, FILTER_VALIDATE_BOOL)) {
                    throw ValidationException::withMessages([
                        'same_equipment_confirmation' => 'Confirm the same equipment and condition before continuing.',
                    ]);
                }

                $trip = $this->tripForPreTripInspection((int) ($action->getArguments()['trip'] ?? 0));
                $source = $this->sameDayInspectionForReuse($trip, (int) ($action->getArguments()['inspection'] ?? 0));
                $user = auth()->user();

                DB::transaction(function () use ($trip, $source, $user): void {
                    $inspection = TripPreTripInspection::query()->create([
                        'trip_id' => $trip->getKey(),
                        'user_id' => $user?->getKey(),
                        'driver_id' => $trip->driver_id,
                        'vehicle_configuration_id' => $source->vehicle_configuration_id,
                        'truck_asset_id' => $source->truck_asset_id,
                        'trailer_asset_id' => $source->trailer_asset_id,
                        'piggyback_asset_id' => $source->piggyback_asset_id,
                        'inspection_date' => now('America/Los_Angeles')->toDateString(),
                        'scheduled_date' => $trip->scheduled_date?->toDateString(),
                        'checklist_version' => $source->checklist_version,
                        'report_type' => TripPreTripInspection::TYPE_PRE_TRIP,
                        'prior_report_reviewed_at' => now(),
                        'status' => TripPreTripInspection::STATUS_COMPLETED,
                        'safe_to_operate' => true,
                        'driver_name' => $trip->driver?->name ?? $user?->name ?? $source->driver_name,
                        'vehicle_configuration_snapshot' => $source->vehicle_configuration_snapshot,
                        'equipment_snapshot' => $source->equipment_snapshot,
                        'responses' => [
                            ...($source->responses ?? []),
                            'same_day_inspection_confirmed' => TripPreTripChecklist::RESPONSE_OK,
                        ],
                        'certification_text' => 'I confirm that I am using the exact equipment inspected earlier today, that inspection remains accurate, and I have not noticed a new issue.',
                        'completed_at' => now(),
                    ]);

                    app(VehicleInspectionReportService::class)->attachAssets($inspection, [
                        'truck' => $source->truckAsset,
                        'trailer' => $source->trailerAsset,
                        'piggyback' => $source->piggybackAsset,
                    ]);
                });

                $this->refreshTripPreTripInspectionView();
                Notification::make()->title('Today’s inspection confirmed for this trip')->success()->send();
            });
    }

    public function completeTripDailyVehicleReportAction(): Action
    {
        return Action::make('completeTripDailyVehicleReport')
            ->modalHeading(fn (Action $action): string => 'End-of-day vehicle report — '.$this
                ->tripForDailyVehicleReport((int) ($action->getArguments()['trip'] ?? 0))->trip_number)
            ->modalDescription('Submit this after your final use of this vehicle combination for the day. Report any new issue or confirm that none was found.')
            ->modalSubmitActionLabel('Submit daily report')
            ->modalWidth('3xl')
            ->extraModalWindowAttributes(['class' => 'team-pre-trip-inspection-modal'])
            ->stickyModalHeader()
            ->schema(function (Action $action): array {
                $trip = $this->tripForDailyVehicleReport((int) ($action->getArguments()['trip'] ?? 0));
                $configuration = $trip->vehicleConfiguration;
                $roles = ['truck' => 'Truck / tractor'];

                if (TripPreTripChecklist::usesTrailer($configuration)) {
                    $roles['trailer'] = 'Trailer';
                }

                if (TripPreTripChecklist::usesPiggyback($configuration)) {
                    $roles['piggyback'] = 'Piggyback forklift';
                }

                $fields = [
                    Select::make('truck_asset_id')
                        ->label('Truck / tractor')
                        ->options(fn (): array => $this->inspectionAssetOptions($this->truckAssetCategories($configuration), $trip))
                        ->searchable()->preload()->required(),
                    Select::make('trailer_asset_id')
                        ->label('Trailer')
                        ->options(fn (): array => $this->inspectionAssetOptions(['trailer'], $trip))
                        ->searchable()->preload()->required()
                        ->visible(TripPreTripChecklist::usesTrailer($configuration)),
                    Select::make('piggyback_asset_id')
                        ->label('Piggyback forklift')
                        ->options(fn (): array => $this->inspectionAssetOptions(['piggyback_forklift'], $trip))
                        ->searchable()->preload()->required()
                        ->visible(TripPreTripChecklist::usesPiggyback($configuration)),
                ];

                foreach (TripDailyVehicleReportChecklist::items() as $key => $item) {
                    $fields[] = Radio::make("daily_responses.{$key}")
                        ->label($item['label'])
                        ->helperText($item['helper_text'])
                        ->options([
                            TripPreTripChecklist::RESPONSE_OK => 'No new issue',
                            TripPreTripChecklist::RESPONSE_DEFECT => 'Report issue',
                        ])
                        ->inline()
                        ->required()
                        ->live()
                        ->columnSpanFull();
                }

                $fields[] = Repeater::make('daily_defects')
                    ->label('Issues found')
                    ->schema([
                        Select::make('asset_role')->label('Equipment')->options($roles)->required(),
                        TextInput::make('component')->label('What part or system?')->required()->maxLength(255),
                        Textarea::make('description')->label('What did you notice?')->helperText('Describe what you saw, heard, felt, or smelled. You do not need to diagnose it.')->required()->rows(3)->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->addActionLabel('Add another issue')
                    ->minItems(1)
                    ->required()
                    ->visible(fn (Get $get): bool => $this->dailyResponsesHaveIssues($get('daily_responses')))
                    ->columnSpanFull();

                $fields[] = Radio::make('operating_concern')
                    ->label('How did the equipment seem when you finished?')
                    ->options([
                        TripPreTripInspectionDefect::DRIVER_ASSESSMENT_REVIEW => 'Needs manager review — no immediate loss of control or obvious danger noticed',
                        TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP => 'Stop — it appears immediately unsafe and should not be used again before review',
                    ])
                    ->helperText('Management makes the repair-before-dispatch decision. Report your observation honestly; you are not expected to diagnose the cause.')
                    ->default(TripPreTripInspectionDefect::DRIVER_ASSESSMENT_REVIEW)
                    ->visible(fn (Get $get): bool => $this->dailyResponsesHaveIssues($get('daily_responses')))
                    ->required(fn (Get $get): bool => $this->dailyResponsesHaveIssues($get('daily_responses')))
                    ->columnSpanFull();

                $fields[] = Checkbox::make('certification')
                    ->label('I certify that this report identifies the equipment I operated today and truthfully reports any new problem, or confirms that none was found.')
                    ->accepted()->required()->columnSpanFull();

                return $fields;
            })
            ->fillForm(function (Action $action): array {
                $trip = $this->tripForDailyVehicleReport((int) ($action->getArguments()['trip'] ?? 0));
                $preTrip = $trip->currentPreTripInspection();

                return [
                    'truck_asset_id' => $preTrip?->truck_asset_id,
                    'trailer_asset_id' => $preTrip?->trailer_asset_id,
                    'piggyback_asset_id' => $preTrip?->piggyback_asset_id,
                    'daily_responses' => [],
                    'daily_defects' => [],
                    'operating_concern' => null,
                    'certification' => false,
                ];
            })
            ->action(function (Action $action, array $data): void {
                $trip = $this->tripForDailyVehicleReport((int) ($action->getArguments()['trip'] ?? 0));

                if ($trip->currentDailyVehicleReport()) {
                    throw ValidationException::withMessages(['daily_responses' => 'An end-of-day report has already been submitted for this trip.']);
                }

                $configuration = $trip->vehicleConfiguration;
                $truck = $this->inspectionAsset($data['truck_asset_id'] ?? null, $this->truckAssetCategories($configuration), 'truck_asset_id', $trip);
                $trailer = TripPreTripChecklist::usesTrailer($configuration)
                    ? $this->inspectionAsset($data['trailer_asset_id'] ?? null, ['trailer'], 'trailer_asset_id', $trip)
                    : null;
                $piggyback = TripPreTripChecklist::usesPiggyback($configuration)
                    ? $this->inspectionAsset($data['piggyback_asset_id'] ?? null, ['piggyback_forklift'], 'piggyback_asset_id', $trip)
                    : null;
                $dailyResponses = collect($data['daily_responses'] ?? []);
                $normalizedDailyResponses = [];

                foreach (TripDailyVehicleReportChecklist::items() as $key => $item) {
                    $response = $dailyResponses->get($key);

                    if (! in_array($response, [TripPreTripChecklist::RESPONSE_OK, TripPreTripChecklist::RESPONSE_DEFECT], true)) {
                        throw ValidationException::withMessages([
                            "daily_responses.{$key}" => 'Complete this daily vehicle report item.',
                        ]);
                    }

                    $normalizedDailyResponses[$key] = $response;
                }

                $dailyHasIssues = collect($normalizedDailyResponses)->contains(TripPreTripChecklist::RESPONSE_DEFECT);
                $reportedDefects = $dailyHasIssues ? collect($data['daily_defects'] ?? []) : collect();

                if ($dailyHasIssues && $reportedDefects->isEmpty()) {
                    throw ValidationException::withMessages(['daily_defects' => 'Describe at least one issue.']);
                }

                $operatingConcern = $dailyHasIssues ? ($data['operating_concern'] ?? null) : null;

                if ($dailyHasIssues && ! in_array($operatingConcern, [
                    TripPreTripInspectionDefect::DRIVER_ASSESSMENT_REVIEW,
                    TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP,
                ], true)) {
                    throw ValidationException::withMessages([
                        'operating_concern' => 'Tell us whether this needs review or appears immediately unsafe.',
                    ]);
                }

                if (! filter_var($data['certification'] ?? false, FILTER_VALIDATE_BOOL)) {
                    throw ValidationException::withMessages([
                        'certification' => 'You must certify this daily vehicle report before submitting.',
                    ]);
                }

                $assets = ['truck' => $truck, 'trailer' => $trailer, 'piggyback' => $piggyback];
                $safeToOperate = $reportedDefects->isEmpty();
                $user = auth()->user();
                $driverName = $trip->driver?->name ?? $user?->name ?? 'Unknown driver';

                $inspection = DB::transaction(function () use ($trip, $configuration, $assets, $reportedDefects, $normalizedDailyResponses, $safeToOperate, $user, $driverName, $operatingConcern): TripPreTripInspection {
                    $defectLabels = $reportedDefects->mapWithKeys(fn (array $defect, int $index): array => [
                        'daily_'.($index + 1) => $defect['component'],
                    ])->all();
                    $defectNotes = $reportedDefects->map(fn (array $defect): string => "{$defect['component']}: {$defect['description']}")->join("\n");
                    $service = app(VehicleInspectionReportService::class);
                    $inspection = TripPreTripInspection::query()->create([
                        'trip_id' => $trip->getKey(),
                        'user_id' => $user?->getKey(),
                        'driver_id' => $trip->driver_id,
                        'vehicle_configuration_id' => $configuration?->getKey(),
                        'truck_asset_id' => $assets['truck']->getKey(),
                        'trailer_asset_id' => $assets['trailer']?->getKey(),
                        'piggyback_asset_id' => $assets['piggyback']?->getKey(),
                        'inspection_date' => now('America/Los_Angeles')->toDateString(),
                        'scheduled_date' => $trip->scheduled_date?->toDateString(),
                        'checklist_version' => TripDailyVehicleReportChecklist::VERSION,
                        'report_type' => TripPreTripInspection::TYPE_DAILY_REPORT,
                        'status' => $safeToOperate ? TripPreTripInspection::STATUS_COMPLETED : TripPreTripInspection::STATUS_DEFECT_REPORTED,
                        'safe_to_operate' => $safeToOperate,
                        'driver_name' => $driverName,
                        'vehicle_configuration_snapshot' => $configuration ? [
                            'id' => $configuration->getKey(), 'code' => $configuration->code, 'name' => $configuration->name,
                        ] : null,
                        'equipment_snapshot' => collect($assets)->map(fn ($asset) => $this->inspectionAssetSnapshot($asset))->all(),
                        'responses' => $normalizedDailyResponses,
                        'defects' => $defectLabels ?: null,
                        'defect_notes' => $defectNotes ?: null,
                        'certification_text' => 'I certify that this daily vehicle report truthfully identifies the equipment operated and all new issues found, or states that none were found.',
                        'completed_at' => now(),
                    ]);

                    $service->attachAssets($inspection, $assets);

                    if ($reportedDefects->isNotEmpty()) {
                        $service->createDefects($inspection, $reportedDefects->map(fn (array $defect, int $index): array => [
                            'component_key' => 'daily_'.($index + 1),
                            'component_label' => $defect['component'],
                            'description' => $defect['description'],
                            'asset' => $assets[$defect['asset_role']] ?? null,
                            'driver_assessment' => $operatingConcern,
                        ])->all());
                    }

                    return $inspection;
                });

                if (! $safeToOperate) {
                    $recipients = app(VehicleInspectionNotificationRecipients::class)
                        ->forInspection($inspection, $user?->getKey());
                    NotificationFacade::send($recipients, new TripPreTripDefectReported($inspection));
                }

                $this->refreshTripPreTripInspectionView();
                Notification::make()->title($safeToOperate ? 'Daily vehicle report recorded' : 'Issues reported')->color($safeToOperate ? 'success' : 'warning')->send();
            });
    }

    public function viewTripVehicleInspectionHistoryAction(): Action
    {
        return Action::make('viewTripVehicleInspectionHistory')
            ->registerModalActions([
                $this->reviewTripInspectionIssueAction(),
            ])
            ->modalHeading('Previous vehicle inspection reports')
            ->modalContent(function (Action $action) {
                $trip = $this->tripForInspectionHistory((int) ($action->getArguments()['trip'] ?? 0));

                return view('filament.team.vehicle-inspection-history', [
                    'reports' => $this->vehicleInspectionHistoryForTrip($trip),
                ]);
            })
            ->modalAutofocus(false)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('4xl')
            ->extraModalWindowAttributes(['class' => 'team-pre-trip-inspection-modal'])
            ->stickyModalHeader();
    }

    public function reviewTripInspectionIssueAction(): Action
    {
        return Action::make('reviewTripInspectionIssue')
            ->modalHeading(function (Action $action): string {
                $issue = $this->tripInspectionIssueForReview((int) ($action->getArguments()['issue'] ?? 0));

                return "Operating review — {$issue->asset?->display_name}";
            })
            ->modalDescription('Record the carrier’s decision. The driver reported an observation and is not expected to diagnose the equipment.')
            ->modalSubmitActionLabel('Record decision')
            ->modalWidth('lg')
            ->extraModalWindowAttributes(['class' => 'team-pre-trip-inspection-modal'])
            ->schema([
                Radio::make('operating_decision')
                    ->label('Carrier decision')
                    ->options([
                        TripPreTripInspectionDefect::OPERATING_DECISION_MAY_OPERATE => 'May operate — correction is not required before dispatch',
                        TripPreTripInspectionDefect::OPERATING_DECISION_OUT_OF_SERVICE => 'Out of service — repair is required before operation',
                    ])
                    ->required(),
                Textarea::make('review_notes')
                    ->label('Reason for decision')
                    ->helperText('Record what was checked and why operation is permitted or prohibited.')
                    ->required()
                    ->rows(4),
            ])
            ->action(function (Action $action, array $data): void {
                $issue = $this->tripInspectionIssueForReview((int) ($action->getArguments()['issue'] ?? 0));
                $service = app(VehicleInspectionReportService::class);

                if ($data['operating_decision'] === TripPreTripInspectionDefect::OPERATING_DECISION_MAY_OPERATE) {
                    $service->certifyResolution(
                        $issue,
                        TripPreTripInspectionDefect::STATUS_CORRECTION_NOT_REQUIRED,
                        $data['review_notes'],
                        auth()->id(),
                    );
                } else {
                    $service->requireRepairBeforeOperation(
                        $issue,
                        $data['review_notes'],
                        auth()->id(),
                    );
                }

                $this->refreshTripPreTripInspectionView();
                Notification::make()->title('Operating decision recorded')->success()->send();
            });
    }

    protected function tripForPreTripInspection(int $tripId): Trip
    {
        $trip = Trip::query()
            ->with(['driver', 'vehicleConfiguration', 'orders'])
            ->findOrFail($tripId);

        if (! $trip->isAssignedDriver(auth()->user())
            || ! $this->deliveryTripPreTripInspectionIsInScope($trip)) {
            throw new AuthorizationException('Only the assigned driver can complete this trip inspection.');
        }

        return $trip;
    }

    protected function tripPreTripInspectionForView(int $inspectionId): TripPreTripInspection
    {
        $inspection = TripPreTripInspection::query()
            ->with(['trip.driver', 'trip.orders', 'vehicleConfiguration', 'inspectionDefects.asset', 'inspectionDefects.reviewedBy', 'inspectionDefects.resolvedBy'])
            ->findOrFail($inspectionId);
        $user = auth()->user();

        if (! ($inspection->trip->isAssignedDriver($user)
            || ($user?->can('manage delivery trip dispatch') ?? false))
            || ! $this->deliveryTripPreTripInspectionIsInScope($inspection->trip)) {
            throw new AuthorizationException('You cannot view this trip inspection.');
        }

        return $inspection;
    }

    protected function sameDayInspectionForReuse(Trip $trip, int $inspectionId): TripPreTripInspection
    {
        $inspection = $trip->reusablePreTripInspection();

        if (! $inspection || $inspection->getKey() !== $inspectionId) {
            throw new AuthorizationException('That inspection cannot be reused for this trip. Start a new inspection instead.');
        }

        return $inspection;
    }

    protected function tripForDailyVehicleReport(int $tripId): Trip
    {
        $trip = $this->tripForPreTripInspection($tripId);

        if ($trip->scheduled_date?->isFuture()) {
            throw new AuthorizationException('The end-of-day report cannot be submitted before the trip date.');
        }

        return $trip;
    }

    protected function tripForInspectionHistory(int $tripId): Trip
    {
        $trip = Trip::query()->with(['driver', 'vehicleConfiguration', 'orders', 'preTripInspections'])->findOrFail($tripId);
        $user = auth()->user();

        if (! ($trip->isAssignedDriver($user) || ($user?->can('manage delivery trip dispatch') ?? false))
            || ! $this->deliveryTripPreTripInspectionIsInScope($trip)) {
            throw new AuthorizationException('You cannot view this vehicle inspection history.');
        }

        return $trip;
    }

    protected function tripInspectionIssueForReview(int $issueId): TripPreTripInspectionDefect
    {
        $issue = TripPreTripInspectionDefect::query()
            ->with(['asset', 'inspection.trip.driver', 'inspection.trip.orders'])
            ->findOrFail($issueId);
        $trip = $issue->inspection->trip;
        $user = auth()->user();

        if (! ($user?->can('manage delivery trip dispatch') ?? false)
            || ! $issue->isOpen()
            || ! $this->deliveryTripPreTripInspectionIsInScope($trip)) {
            throw new AuthorizationException('You cannot make an operating decision for this issue.');
        }

        return $issue;
    }

    /** @return array<int, Step> */
    protected function preTripInspectionSteps(Trip $trip): array
    {
        $configuration = $trip->vehicleConfiguration;
        $steps = [];

        foreach (TripPreTripChecklist::sections($configuration) as $section) {
            $fields = [];

            if ($section['key'] === 'review') {
                $fields[] = Select::make('truck_asset_id')
                    ->label($configuration?->configuration_type === VehicleConfiguration::TYPE_BOOM_TRUCK
                        ? 'Truck'
                        : 'Tractor / truck')
                    ->options(fn (): array => $this->inspectionAssetOptions($this->truckAssetCategories($configuration), $trip))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->helperText('Select the actual power unit being inspected.');

                if (TripPreTripChecklist::usesTrailer($configuration)) {
                    $fields[] = Select::make('trailer_asset_id')
                        ->label('Trailer')
                        ->options(fn (): array => $this->inspectionAssetOptions(['trailer'], $trip))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required();
                }

                if (TripPreTripChecklist::usesPiggyback($configuration)) {
                    $fields[] = Select::make('piggyback_asset_id')
                        ->label('Piggyback forklift')
                        ->options(fn (): array => $this->inspectionAssetOptions(['piggyback_forklift'], $trip))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required();
                }

                $fields[] = View::make('filament.team.previous-vehicle-inspection-reports')
                    ->viewData(fn (Get $get): array => $this->previousVehicleInspectionViewData([
                        $get('truck_asset_id'),
                        $get('trailer_asset_id'),
                        $get('piggyback_asset_id'),
                    ]));
            }

            foreach ($section['items'] as $key => $item) {
                $options = [
                    TripPreTripChecklist::RESPONSE_OK => 'Looks good',
                    TripPreTripChecklist::RESPONSE_DEFECT => 'Report issue',
                ];

                if ($item['allow_not_applicable'] ?? false) {
                    $options[TripPreTripChecklist::RESPONSE_NOT_APPLICABLE] = 'N/A';
                }

                $fields[] = Radio::make("responses.{$key}")
                    ->label($item['label'])
                    ->helperText($item['helper_text'] ?? null)
                    ->options($options)
                    ->inline()
                    ->required()
                    ->live();
            }

            $steps[] = Step::make($section['label'])
                ->description($section['description'])
                ->schema($fields)
                ->columns(1);
        }

        $steps[] = Step::make('Certify')
            ->description('Review any issues and submit the driver-signed inspection record.')
            ->schema([
                Textarea::make('defect_notes')
                    ->label('What did you notice?')
                    ->placeholder('Describe what you saw, heard, felt, or smelled. You do not need to diagnose the cause.')
                    ->rows(4)
                    ->visible(fn (Get $get): bool => $this->preTripResponsesHaveDefects($get('responses')))
                    ->required(fn (Get $get): bool => $this->preTripResponsesHaveDefects($get('responses'))),
                Radio::make('operating_concern')
                    ->label('How does the equipment seem right now?')
                    ->options([
                        TripPreTripInspectionDefect::DRIVER_ASSESSMENT_REVIEW => 'Needs manager review — I noticed something, but no immediate loss of control or obvious danger',
                        TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP => 'Stop — brakes, steering, tire/wheel, coupling, load, major leak, or another condition appears immediately unsafe',
                    ])
                    ->helperText('Report what you observe honestly. Management decides whether repair is required before dispatch; you are not expected to diagnose it.')
                    ->default(TripPreTripInspectionDefect::DRIVER_ASSESSMENT_REVIEW)
                    ->visible(fn (Get $get): bool => $this->preTripResponsesHaveDefects($get('responses')))
                    ->required(fn (Get $get): bool => $this->preTripResponsesHaveDefects($get('responses'))),
                Checkbox::make('certification')
                    ->label(fn (Get $get): string => TripPreTripChecklist::certificationText(
                        ! $this->preTripResponsesHaveDefects($get('responses')),
                    ))
                    ->accepted()
                    ->required(),
            ]);

        return $steps;
    }

    protected function preTripResponsesHaveDefects(mixed $responses): bool
    {
        return collect(is_array($responses) ? $responses : [])
            ->contains(TripPreTripChecklist::RESPONSE_DEFECT);
    }

    protected function dailyResponsesHaveIssues(mixed $responses): bool
    {
        return collect(is_array($responses) ? $responses : [])
            ->contains(TripPreTripChecklist::RESPONSE_DEFECT);
    }

    /** @return array<int, string> */
    protected function truckAssetCategories(?VehicleConfiguration $configuration): array
    {
        return $configuration?->configuration_type === VehicleConfiguration::TYPE_BOOM_TRUCK
            ? ['boom_truck', 'truck']
            : ['tractor', 'truck'];
    }

    /** @param array<int, string> $categories */
    protected function inspectionAssetOptions(array $categories, Trip $trip): array
    {
        $plantLocationId = $this->inspectionPlantLocationId($trip);

        if (! $plantLocationId) {
            return [];
        }

        return MaintenanceAsset::query()
            ->whereIn('category', $categories)
            ->where('location_id', $plantLocationId)
            ->whereIn('status', ['operational', 'restricted'])
            ->orderByRaw("CASE WHEN status = 'operational' THEN 0 ELSE 1 END")
            ->orderBy('asset_tag')
            ->get()
            ->mapWithKeys(fn (MaintenanceAsset $asset): array => [
                $asset->getKey() => $asset->display_name.($asset->status === 'restricted' ? ' (restricted)' : ''),
            ])
            ->all();
    }

    /** @param array<int, string> $categories */
    protected function inspectionAsset(mixed $assetId, array $categories, string $field, Trip $trip): MaintenanceAsset
    {
        $plantLocationId = $this->inspectionPlantLocationId($trip);

        if (! $plantLocationId) {
            throw ValidationException::withMessages([
                $field => 'This trip does not have one valid originating plant. Review its orders before inspecting equipment.',
            ]);
        }

        $asset = MaintenanceAsset::query()
            ->whereKey($assetId)
            ->whereIn('category', $categories)
            ->where('location_id', $plantLocationId)
            ->whereIn('status', ['operational', 'restricted'])
            ->first();

        if (! $asset) {
            throw ValidationException::withMessages([
                $field => 'Select an available asset of the correct type from this trip’s plant.',
            ]);
        }

        return $asset;
    }

    protected function inspectionPlantLocationId(Trip $trip): ?int
    {
        $plants = $trip->orderedDeliveryOrders()
            ->pluck('plant_location')
            ->filter()
            ->map(fn ($plant): string => (string) $plant === 'colma_locals' ? 'colma_main' : (string) $plant)
            ->unique()
            ->values();

        if ($plants->count() !== 1) {
            return null;
        }

        $plantName = match ($plants->first()) {
            'colma_main' => 'Christy Vault - Colma',
            'tulare_plant' => 'Christy Vault - Tulare',
            default => null,
        };

        if (! $plantName) {
            return null;
        }

        return Location::query()
            ->christyVault()
            ->where('name', $plantName)
            ->value('id');
    }

    protected function inspectionAssetSnapshot(?MaintenanceAsset $asset): ?array
    {
        if (! $asset) {
            return null;
        }

        return [
            'id' => $asset->getKey(),
            'asset_tag' => $asset->asset_tag,
            'name' => $asset->name,
            'category' => $asset->category,
            'status' => $asset->status,
            'license_plate' => $asset->license_plate,
            'serial_number' => $asset->serial_number,
        ];
    }

    protected function inspectionAssetRoleForComponent(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'trailer_') => 'trailer',
            str_starts_with($key, 'piggyback_') => 'piggyback',
            default => 'truck',
        };
    }

    /** @param array<int, mixed> $assetIds */
    protected function previousVehicleInspectionViewData(array $assetIds): array
    {
        $assetIds = collect($assetIds)->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        if ($assetIds->isEmpty()) {
            return ['reports' => collect(), 'openDefects' => collect()];
        }

        return [
            'reports' => TripPreTripInspection::query()
                ->whereHas('assets', fn ($query) => $query->whereIn('maintenance_assets.id', $assetIds))
                ->with(['assets', 'inspectionDefects.resolvedBy'])
                ->latest('completed_at')->limit(5)->get(),
            'openDefects' => TripPreTripInspectionDefect::query()
                ->whereIn('maintenance_asset_id', $assetIds)
                ->where('status', TripPreTripInspectionDefect::STATUS_OPEN)
                ->with('asset')->latest('reported_at')->get(),
        ];
    }

    protected function vehicleInspectionHistoryForTrip(Trip $trip)
    {
        $assetIds = $trip->preTripInspections
            ->flatMap(fn (TripPreTripInspection $inspection): array => [
                $inspection->truck_asset_id, $inspection->trailer_asset_id, $inspection->piggyback_asset_id,
            ])->filter()->unique();

        return TripPreTripInspection::query()
            ->when($assetIds->isNotEmpty(), fn ($query) => $query->whereHas(
                'assets',
                fn ($assetQuery) => $assetQuery->whereIn('maintenance_assets.id', $assetIds),
            ), fn ($query) => $query->where('driver_id', $trip->driver_id))
            ->with(['assets', 'inspectionDefects.asset', 'inspectionDefects.resolvedBy', 'trip'])
            ->latest('completed_at')->limit(20)->get();
    }

    abstract protected function deliveryTripPreTripInspectionIsInScope(Trip $trip): bool;

    abstract protected function refreshTripPreTripInspectionView(): void;
}
