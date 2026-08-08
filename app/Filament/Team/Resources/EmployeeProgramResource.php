<?php

namespace App\Filament\Team\Resources;

use App\Filament\Team\Resources\EmployeeProgramResource\Pages\CreateEmployeeProgram;
use App\Filament\Team\Resources\EmployeeProgramResource\Pages\EditEmployeeProgram;
use App\Filament\Team\Resources\EmployeeProgramResource\Pages\ListEmployeePrograms;
use App\Filament\Team\Resources\EmployeeProgramResource\Pages\ViewEmployeeProgram;
use App\Models\EmployeeProgram;
use App\Models\EmployeeProgramItem;
use App\Models\Position;
use App\Models\StandardOperatingProcedure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\View as ViewLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EmployeeProgramResource extends Resource
{
    protected static ?string $model = EmployeeProgram::class;

    protected static ?string $slug = 'programs';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Employee Resources';

    protected static ?string $navigationLabel = 'Programs';

    protected static ?string $modelLabel = 'program';

    protected static ?string $pluralModelLabel = 'Programs';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Program details')
                ->description('A program groups procedures and related resources around a broader responsibility or topic.')
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255),
                    Select::make('category')
                        ->options(EmployeeProgram::categoryOptions())
                        ->searchable()
                        ->native(false)
                        ->required(),
                    Select::make('owner_user_id')
                        ->label('Program owner')
                        ->relationship('owner', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('The manager responsible for keeping this collection useful and current.'),
                    Textarea::make('summary')
                        ->helperText('A short explanation employees will see in the program library.')
                        ->rows(3)
                        ->maxLength(1000),
                ]),

            Section::make('Employee access')
                ->description('Programs do not override procedure permissions. Employees only see linked procedures that also apply to them.')
                ->schema([
                    Select::make('audience')
                        ->options(EmployeeProgram::audienceOptions())
                        ->default(EmployeeProgram::AUDIENCE_ALL_EMPLOYEES)
                        ->required()
                        ->native(false)
                        ->live(),
                    Select::make('positions')
                        ->relationship('positions', 'display_name')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => $get('audience') === EmployeeProgram::AUDIENCE_SELECTED_POSITIONS)
                        ->visible(fn (Get $get): bool => $get('audience') === EmployeeProgram::AUDIENCE_SELECTED_POSITIONS)
                        ->helperText('Employees only need one of the selected positions.'),
                    Select::make('plant_locations')
                        ->label('Plants')
                        ->options(EmployeeProgram::plantOptions())
                        ->multiple()
                        ->native(false)
                        ->helperText('Leave blank for every plant.'),
                ]),

            Section::make('Introduction')
                ->description('Use this for the purpose of the program, who it helps, and how employees should use the material.')
                ->schema([
                    RichEditor::make('introduction')
                        ->label('Program overview')
                        ->json()
                        ->extraInputAttributes([
                            'style' => 'height: 22rem; min-height: 16rem; resize: vertical; overflow: auto;',
                        ])
                        ->toolbarButtons([
                            ['bold', 'italic', 'underline', 'link'],
                            ['h2', 'h3'],
                            ['blockquote', 'bulletList', 'orderedList'],
                            ['table'],
                            ['undo', 'redo'],
                        ]),
                ]),

            Section::make('Program contents')
                ->description('Create ordered sections, then add procedures, private files or videos, and trusted external links. Drag items to arrange them.')
                ->schema([
                    Repeater::make('sections')
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->schema([
                            TextInput::make('title')
                                ->label('Section title')
                                ->required()
                                ->maxLength(255),
                            Textarea::make('description')
                                ->label('Section introduction')
                                ->rows(2)
                                ->maxLength(1000),
                            Repeater::make('items')
                                ->relationship()
                                ->orderColumn('sort_order')
                                ->schema([
                                    Select::make('type')
                                        ->options(EmployeeProgramItem::typeOptions())
                                        ->default(EmployeeProgramItem::TYPE_PROCEDURE)
                                        ->required()
                                        ->native(false)
                                        ->live(),
                                    Select::make('standard_operating_procedure_id')
                                        ->label('Policy or procedure')
                                        ->options(fn (): array => StandardOperatingProcedure::query()
                                            ->whereNull('archived_at')
                                            ->whereNotNull('current_revision_id')
                                            ->orderBy('title')
                                            ->get()
                                            ->mapWithKeys(fn (StandardOperatingProcedure $document): array => [
                                                $document->getKey() => "{$document->document_label} · {$document->code} · {$document->title}",
                                            ])
                                            ->all())
                                        ->searchable()
                                        ->preload()
                                        ->required(fn (Get $get): bool => $get('type') === EmployeeProgramItem::TYPE_PROCEDURE)
                                        ->visible(fn (Get $get): bool => $get('type') === EmployeeProgramItem::TYPE_PROCEDURE),
                                    FileUpload::make('file_path')
                                        ->label('File or video')
                                        ->disk('local')
                                        ->directory('programs/materials')
                                        ->visibility('private')
                                        ->storeFileNamesIn('original_name')
                                        ->acceptedFileTypes([
                                            'image/jpeg',
                                            'image/png',
                                            'image/webp',
                                            'image/gif',
                                            'video/mp4',
                                            'video/webm',
                                            'video/quicktime',
                                            'application/pdf',
                                            'application/msword',
                                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                            'application/vnd.ms-excel',
                                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                            'application/vnd.ms-powerpoint',
                                            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                            'text/plain',
                                        ])
                                        ->maxSize(51200)
                                        ->openable()
                                        ->downloadable()
                                        ->required(fn (Get $get): bool => $get('type') === EmployeeProgramItem::TYPE_FILE)
                                        ->visible(fn (Get $get): bool => $get('type') === EmployeeProgramItem::TYPE_FILE),
                                    TextInput::make('external_url')
                                        ->label('External URL')
                                        ->url()
                                        ->maxLength(2000)
                                        ->required(fn (Get $get): bool => $get('type') === EmployeeProgramItem::TYPE_LINK)
                                        ->visible(fn (Get $get): bool => $get('type') === EmployeeProgramItem::TYPE_LINK),
                                    TextInput::make('title')
                                        ->label('Display title')
                                        ->helperText('Optional for procedures and files. Required for external links.')
                                        ->required(fn (Get $get): bool => $get('type') === EmployeeProgramItem::TYPE_LINK)
                                        ->maxLength(255),
                                    Textarea::make('description')
                                        ->label('Why this is included')
                                        ->rows(2)
                                        ->maxLength(1000),
                                    Toggle::make('required_for_completion')
                                        ->label('Required for training completion')
                                        ->helperText('For acknowledgment policies, the employee must acknowledge the exact current revision before completing an assignment.'),
                                ])
                                ->itemLabel(function (array $state): string {
                                    if (filled($state['title'] ?? null)) {
                                        return (string) $state['title'];
                                    }

                                    if (($state['type'] ?? null) === EmployeeProgramItem::TYPE_PROCEDURE) {
                                        return StandardOperatingProcedure::query()
                                            ->find($state['standard_operating_procedure_id'] ?? null)?->title
                                            ?? 'Procedure';
                                    }

                                    return EmployeeProgramItem::typeOptions()[$state['type'] ?? ''] ?? 'Program item';
                                })
                                ->addActionLabel('Add program item')
                                ->collapsible()
                                ->reorderable()
                                ->defaultItems(0),
                        ])
                        ->itemLabel(fn (array $state): string => (string) ($state['title'] ?? 'Program section'))
                        ->addActionLabel('Add section')
                        ->collapsible()
                        ->reorderable()
                        ->defaultItems(0),
                ]),

            Section::make('Training and questionnaire')
                ->description('A program is the content employees review. Enable training here so managers can assign that program to specific employees and track completion.')
                ->schema([
                    Toggle::make('training_enabled')
                        ->label('Make this program assignable as training')
                        ->helperText('This does not automatically require anyone to complete it. After publishing, use Team → Training → Assign training to select employees.')
                        ->default(false)
                        ->live(),
                    TextInput::make('estimated_minutes')
                        ->label('Estimated time')
                        ->suffix('minutes')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1440)
                        ->visible(fn (Get $get): bool => (bool) $get('training_enabled')),
                    TextInput::make('passing_score')
                        ->label('Passing score')
                        ->suffix('%')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->default(80)
                        ->required(fn (Get $get): bool => (bool) $get('training_enabled'))
                        ->visible(fn (Get $get): bool => (bool) $get('training_enabled')),
                    Repeater::make('trainingQuestions')
                        ->label('Questionnaire')
                        ->helperText('Optional. Assigned employees must reach the passing score before the training can be completed.')
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->visible(fn (Get $get): bool => (bool) $get('training_enabled'))
                        ->schema([
                            Textarea::make('prompt')
                                ->label('Question')
                                ->rows(2)
                                ->required()
                                ->columnSpanFull(),
                            Repeater::make('options')
                                ->label('Answer choices')
                                ->schema([
                                    TextInput::make('label')
                                        ->label('Answer')
                                        ->required()
                                        ->maxLength(1000),
                                    Toggle::make('correct')
                                        ->label('Correct answer'),
                                ])
                                ->columns(['default' => 1, 'md' => 2])
                                ->minItems(2)
                                ->defaultItems(2)
                                ->addActionLabel('Add answer choice')
                                ->reorderable()
                                ->columnSpanFull(),
                            Textarea::make('explanation')
                                ->label('Answer explanation')
                                ->helperText('Keep the reason for the correct answer with the question. This can be used in review and future feedback screens.')
                                ->rows(2)
                                ->columnSpanFull(),
                            Toggle::make('is_active')
                                ->label('Include this question')
                                ->default(true),
                        ])
                        ->itemLabel(fn (array $state): string => str((string) ($state['prompt'] ?? 'New question'))->limit(80)->toString())
                        ->addActionLabel('Add question')
                        ->collapsible()
                        ->reorderable()
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ])
                ->columns(['default' => 1, 'md' => 2]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewLayout::make('filament.team.resources.employee-program-resource.components.library-card'),
            ])
            ->contentGrid([
                'default' => 1,
                'md' => 2,
                'xl' => 3,
            ])
            ->extraAttributes(['class' => 'procedure-library-table'])
            ->recordClasses('group')
            ->recordUrl(fn (EmployeeProgram $record): string => static::getUrl('view', ['record' => $record]))
            ->searchable()
            ->searchDebounce('300ms')
            ->searchPlaceholder('Search programs, sections, procedures, or resources…')
            ->searchUsing(function (Builder $query, string $search): void {
                $terms = str($search)->squish()->explode(' ')->filter();

                foreach ($terms as $word) {
                    $term = '%'.addcslashes($word, '%_\\').'%';
                    $query->where(function (Builder $searchQuery) use ($term): void {
                        $searchQuery
                            ->where('title', 'like', $term)
                            ->orWhere('summary', 'like', $term)
                            ->orWhere('category', 'like', $term)
                            ->orWhereRaw('CAST(introduction AS TEXT) LIKE ?', [$term])
                            ->orWhereHas('sections', fn (Builder $sectionQuery): Builder => $sectionQuery
                                ->where('title', 'like', $term)
                                ->orWhere('description', 'like', $term)
                                ->orWhereHas('items', fn (Builder $itemQuery): Builder => $itemQuery
                                    ->where('title', 'like', $term)
                                    ->orWhere('description', 'like', $term)
                                    ->orWhereHas('procedure', fn (Builder $procedureQuery): Builder => $procedureQuery
                                        ->where('code', 'like', $term)
                                        ->orWhere('title', 'like', $term))));
                    });
                }
            })
            ->defaultSort('title')
            ->defaultGroup('category')
            ->groups([
                Group::make('category')
                    ->label('Topic')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn (EmployeeProgram $record): string => EmployeeProgram::categoryOptions()[$record->category] ?? str($record->category)->headline())
                    ->collapsible(),
            ])
            ->groupingSettingsHidden()
            ->filters([
                SelectFilter::make('category')
                    ->label('Topic')
                    ->options(EmployeeProgram::categoryOptions()),
                SelectFilter::make('status')
                    ->options([
                        EmployeeProgram::STATUS_DRAFT => 'Draft',
                        EmployeeProgram::STATUS_PUBLISHED => 'Published',
                        EmployeeProgram::STATUS_ARCHIVED => 'Archived',
                    ])
                    ->visible(fn (): bool => auth()->user()?->canManagePrograms() ?? false),
                SelectFilter::make('plant')
                    ->label('Applies to plant')
                    ->options(EmployeeProgram::plantOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $plant = $data['value'] ?? null;

                        return $query->when(filled($plant), fn (Builder $plantQuery): Builder => $plantQuery
                            ->where(function (Builder $scope) use ($plant): void {
                                $scope
                                    ->whereNull('plant_locations')
                                    ->orWhereJsonLength('plant_locations', 0)
                                    ->orWhereJsonContains('plant_locations', $plant);
                            }));
                    })
                    ->visible(fn (): bool => auth()->user()?->canManagePrograms() ?? false),
                SelectFilter::make('position_id')
                    ->label('Applies to position')
                    ->options(fn (): array => Position::query()->orderBy('display_name')->pluck('display_name', 'id')->all())
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $positionId = $data['value'] ?? null;

                        return $query->when(filled($positionId), fn (Builder $positionQuery): Builder => $positionQuery
                            ->where(function (Builder $audienceQuery) use ($positionId): void {
                                $audienceQuery
                                    ->where('audience', EmployeeProgram::AUDIENCE_ALL_EMPLOYEES)
                                    ->orWhere(function (Builder $selectedPositionQuery) use ($positionId): void {
                                        $selectedPositionQuery
                                            ->where('audience', EmployeeProgram::AUDIENCE_SELECTED_POSITIONS)
                                            ->whereHas('positions', fn (Builder $relationQuery): Builder => $relationQuery->whereKey($positionId));
                                    });
                            }));
                    })
                    ->visible(fn (): bool => auth()->user()?->canManagePrograms() ?? false),
            ])
            ->filtersFormColumns(2)
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->paginationPageOptions([18, 36, 72])
            ->defaultPaginationPageOption(18)
            ->emptyStateHeading('No programs available')
            ->emptyStateDescription(fn (): string => auth()->user()?->canManagePrograms()
                ? 'Create the first employee resource program.'
                : 'No published programs currently apply to you.');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visibleTo(auth()->user())
            ->with([
                'positions',
                'sections.items.procedure.currentRevision',
            ]);
    }

    public static function getGlobalSearchResults(string $search): Collection
    {
        $query = static::getGlobalSearchEloquentQuery();
        $terms = str($search)->squish()->lower()->explode(' ')->filter();

        foreach ($terms as $word) {
            $term = '%'.addcslashes((string) $word, '%_\\').'%';

            $query->where(function (Builder $searchQuery) use ($term): void {
                $searchQuery
                    ->whereRaw('LOWER(title) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(summary) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(category) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(CAST(introduction AS TEXT)) LIKE ?', [$term])
                    ->orWhereHas('sections', fn (Builder $sectionQuery): Builder => $sectionQuery
                        ->whereRaw('LOWER(title) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(description) LIKE ?', [$term])
                        ->orWhereHas('items', fn (Builder $itemQuery): Builder => $itemQuery
                            ->whereRaw('LOWER(title) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(description) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(original_name) LIKE ?', [$term])
                            ->orWhereHas('procedure', fn (Builder $procedureQuery): Builder => $procedureQuery
                                ->visibleTo(auth()->user())
                                ->where(function (Builder $visibleProcedureQuery) use ($term): void {
                                    $visibleProcedureQuery
                                        ->whereRaw('LOWER(code) LIKE ?', [$term])
                                        ->orWhereRaw('LOWER(title) LIKE ?', [$term])
                                        ->orWhereRaw('LOWER(summary) LIKE ?', [$term])
                                        ->orWhereHas('currentRevision', fn (Builder $revisionQuery): Builder => $revisionQuery
                                            ->whereRaw('LOWER(code) LIKE ?', [$term])
                                            ->orWhereRaw('LOWER(title) LIKE ?', [$term])
                                            ->orWhereRaw('LOWER(summary) LIKE ?', [$term]));
                                }))));
            });
        }

        return $query
            ->limit(static::getGlobalSearchResultsLimit())
            ->get()
            ->map(function (EmployeeProgram $record): ?GlobalSearchResult {
                $url = static::getGlobalSearchResultUrl($record);

                if (blank($url)) {
                    return null;
                }

                return new GlobalSearchResult(
                    title: static::getGlobalSearchResultTitle($record),
                    url: $url,
                    details: [
                        'Topic' => EmployeeProgram::categoryOptions()[$record->category]
                            ?? str($record->category)->headline()->toString(),
                    ],
                );
            })
            ->filter()
            ->values();
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->canViewPrograms()
            && ($user->canManagePrograms() || $user->employee?->is_active));
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof EmployeeProgram
            && $record->isVisibleTo(auth()->user());
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canManagePrograms() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->canManagePrograms() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeePrograms::route('/'),
            'create' => CreateEmployeeProgram::route('/create'),
            'view' => ViewEmployeeProgram::route('/{record}'),
            'edit' => EditEmployeeProgram::route('/{record}/edit'),
        ];
    }
}
