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
                                        ->label('Procedure')
                                        ->options(fn (): array => StandardOperatingProcedure::query()
                                            ->whereNull('archived_at')
                                            ->whereNotNull('current_revision_id')
                                            ->orderBy('title')
                                            ->pluck('title', 'id')
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
