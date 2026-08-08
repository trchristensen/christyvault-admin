<?php

namespace App\Filament\Team\Resources;

use App\Filament\Team\Resources\StandardOperatingProcedureResource\Pages\CreateStandardOperatingProcedure;
use App\Filament\Team\Resources\StandardOperatingProcedureResource\Pages\EditStandardOperatingProcedure;
use App\Filament\Team\Resources\StandardOperatingProcedureResource\Pages\ListStandardOperatingProcedures;
use App\Filament\Team\Resources\StandardOperatingProcedureResource\Pages\ViewStandardOperatingProcedure;
use App\Models\Position;
use App\Models\StandardOperatingProcedure;
use Filament\Forms\Components\DatePicker;
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
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\View as ViewLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class StandardOperatingProcedureResource extends Resource
{
    protected static ?string $model = StandardOperatingProcedure::class;

    protected static ?string $slug = 'procedures';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Employee Resources';

    protected static ?string $navigationLabel = 'Policies & Procedures';

    protected static ?string $modelLabel = 'policy or procedure';

    protected static ?string $pluralModelLabel = 'Policies & Procedures';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Document details')
                ->description('The code remains stable while published revisions preserve exactly what employees were shown.')
                ->schema([
                    Select::make('document_type')
                        ->label('Document type')
                        ->options(StandardOperatingProcedure::typeOptions())
                        ->default(StandardOperatingProcedure::TYPE_PROCEDURE)
                        ->required()
                        ->native(false)
                        ->live(),
                    TextInput::make('code')
                        ->label('Document number')
                        ->placeholder('SOP-DRV-001')
                        ->helperText('Use a short permanent identifier, such as SOP-DRV-001.')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50),
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255),
                    Select::make('category')
                        ->options(StandardOperatingProcedure::categoryOptions())
                        ->searchable()
                        ->native(false)
                        ->required(),
                    Select::make('owner_user_id')
                        ->label('Document owner')
                        ->relationship('owner', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('The manager responsible for reviewing this document.'),
                    Textarea::make('summary')
                        ->rows(3)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ])
                ->columns(['default' => 1, 'xl' => 2]),

            Section::make('Employee access')
                ->description('These settings determine who can find the published procedure in the Team panel.')
                ->schema([
                    Select::make('audience')
                        ->options(StandardOperatingProcedure::audienceOptions())
                        ->default(StandardOperatingProcedure::AUDIENCE_ALL_EMPLOYEES)
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            if ($state === StandardOperatingProcedure::AUDIENCE_MANAGEMENT) {
                                $set('public_qr_enabled', false);
                            }
                        }),
                    Select::make('positions')
                        ->relationship('positions', 'display_name')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => $get('audience') === StandardOperatingProcedure::AUDIENCE_SELECTED_POSITIONS)
                        ->visible(fn (Get $get): bool => $get('audience') === StandardOperatingProcedure::AUDIENCE_SELECTED_POSITIONS)
                        ->helperText('Employees only need one of the selected positions.'),
                    Select::make('plant_locations')
                        ->label('Plants')
                        ->options(StandardOperatingProcedure::plantOptions())
                        ->multiple()
                        ->native(false)
                        ->helperText('Leave blank for every plant.'),
                    Toggle::make('public_qr_enabled')
                        ->label('Allow public QR access')
                        ->visible(fn (Get $get): bool => $get('audience') !== StandardOperatingProcedure::AUDIENCE_MANAGEMENT)
                        ->helperText('Anyone with this document’s QR code can read its current published version without signing in. Use only for non-sensitive content.'),
                ])
                ->columns(['default' => 1, 'xl' => 2]),

            Section::make('Draft content')
                ->description('Saving updates the draft only. Employees continue seeing the previous published version until a manager publishes these changes.')
                ->schema([
                    RichEditor::make('draft_content')
                        ->label('Document')
                        ->helperText('Use this as the full document workspace. Drag the bottom-right corner to make the editor taller or shorter.')
                        ->json()
                        ->extraInputAttributes([
                            'style' => 'height: 36rem; min-height: 28rem; resize: vertical; overflow: auto;',
                        ])
                        ->toolbarButtons([
                            ['bold', 'italic', 'underline', 'link'],
                            ['h2', 'h3'],
                            ['blockquote', 'bulletList', 'orderedList'],
                            ['table'],
                            ['undo', 'redo'],
                        ])
                        ->required()
                        ->columnSpanFull(),
                    Textarea::make('draft_change_summary')
                        ->label('What changed?')
                        ->helperText('Required after the first version so the revision history is understandable.')
                        ->required(fn (?StandardOperatingProcedure $record): bool => $record?->current_revision_id !== null)
                        ->rows(3)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                    DatePicker::make('draft_effective_date')
                        ->label('Effective date')
                        ->default(today())
                        ->maxDate(today())
                        ->native(false)
                        ->required(),
                    DatePicker::make('draft_review_due_date')
                        ->label('Review due')
                        ->after('draft_effective_date')
                        ->native(false),
                ])
                ->columns(['default' => 1, 'xl' => 2]),

            Section::make('Employee acknowledgment')
                ->description('Use this for policies employees must personally acknowledge. The exact statement is preserved with every published revision and signature.')
                ->visible(fn (Get $get): bool => $get('document_type') === StandardOperatingProcedure::TYPE_POLICY)
                ->schema([
                    Toggle::make('acknowledgement_required')
                        ->label('Require employee acknowledgment')
                        ->helperText('Employees acknowledge the current published revision. Publishing a new revision requires a new acknowledgment.')
                        ->live(),
                    Textarea::make('draft_acknowledgement_text')
                        ->label('Acknowledgment statement')
                        ->placeholder('I acknowledge that I received and had an opportunity to review this policy. I understand that I am expected to follow it and know whom to contact with questions.')
                        ->helperText('Write this as acknowledgment of receipt and understanding, not as a claim that the employee agrees with every term.')
                        ->rows(5)
                        ->required(fn (Get $get): bool => (bool) $get('acknowledgement_required'))
                        ->visible(fn (Get $get): bool => (bool) $get('acknowledgement_required'))
                        ->columnSpanFull(),
                ]),

            Section::make('Draft attachments')
                ->description('Images, videos, and documents publish with this revision. Removing one from a later draft does not remove it from an older published version.')
                ->schema([
                    Repeater::make('draft_attachments')
                        ->label('Related material')
                        ->schema([
                            FileUpload::make('path')
                                ->label('File')
                                ->disk('local')
                                ->directory('procedures/attachments')
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
                                ->required(),
                            TextInput::make('title')
                                ->label('Display title')
                                ->placeholder('Uses the original filename when left blank')
                                ->maxLength(255),
                            Textarea::make('description')
                                ->label('Caption or instructions')
                                ->rows(2)
                                ->maxLength(1000),
                            Toggle::make('public_qr_enabled')
                                ->label('Show this attachment on the public QR page')
                                ->helperText('Leave off for internal material. The procedure itself must also have public QR access enabled.'),
                        ])
                        ->itemLabel(fn (array $state): string => filled($state['title'] ?? null)
                            ? (string) $state['title']
                            : (string) ($state['original_name'] ?? 'New attachment'))
                        ->addActionLabel('Add attachment')
                        ->collapsible()
                        ->reorderable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewLayout::make('filament.team.resources.standard-operating-procedure-resource.components.library-card'),
            ])
            ->contentGrid([
                'default' => 1,
                'md' => 2,
                'xl' => 3,
            ])
            ->extraAttributes(['class' => 'procedure-library-table'])
            ->recordClasses('group')
            ->recordUrl(fn (StandardOperatingProcedure $record): string => static::getUrl('view', ['record' => $record]))
            ->searchable()
            ->searchDebounce('300ms')
            ->searchPlaceholder('Search policies, procedures, topics, or content…')
            ->searchUsing(function (Builder $query, string $search): void {
                $terms = str($search)->squish()->explode(' ')->filter();

                foreach ($terms as $word) {
                    $term = '%'.addcslashes($word, '%_\\').'%';

                    if (auth()->user()?->canManageProcedures()) {
                        $query->where(function (Builder $searchQuery) use ($term): void {
                            $searchQuery
                                ->where('code', 'like', $term)
                                ->orWhere('title', 'like', $term)
                                ->orWhere('summary', 'like', $term)
                                ->orWhere('category', 'like', $term)
                                ->orWhere('draft_content', 'like', $term)
                                ->orWhereHas('currentRevision', fn (Builder $revisionQuery): Builder => $revisionQuery
                                    ->where('code', 'like', $term)
                                    ->orWhere('title', 'like', $term)
                                    ->orWhere('summary', 'like', $term)
                                    ->orWhere('content', 'like', $term));
                        });

                        continue;
                    }

                    $query->whereHas('currentRevision', fn (Builder $revisionQuery): Builder => $revisionQuery
                        ->where('code', 'like', $term)
                        ->orWhere('title', 'like', $term)
                        ->orWhere('summary', 'like', $term)
                        ->orWhere('category', 'like', $term)
                        ->orWhere('content', 'like', $term));
                }
            })
            ->defaultSort('title')
            ->defaultGroup('category')
            ->groups([
                Group::make('category')
                    ->label('Topic')
                    ->titlePrefixedWithLabel(false)
                    ->getKeyFromRecordUsing(fn (StandardOperatingProcedure $record): string => static::libraryCategory($record))
                    ->getTitleFromRecordUsing(fn (StandardOperatingProcedure $record): string => StandardOperatingProcedure::categoryOptions()[static::libraryCategory($record)] ?? str(static::libraryCategory($record))->headline())
                    ->getDescriptionFromRecordUsing(fn (StandardOperatingProcedure $record): ?string => StandardOperatingProcedure::categoryDescriptions()[static::libraryCategory($record)] ?? null)
                    ->collapsible(),
            ])
            ->groupingSettingsHidden()
            ->filters([
                SelectFilter::make('document_type')
                    ->label('Document type')
                    ->options(StandardOperatingProcedure::typeOptions()),
                SelectFilter::make('category')
                    ->label('Topic')
                    ->options(StandardOperatingProcedure::categoryOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $category = $data['value'] ?? null;

                        if (blank($category)) {
                            return $query;
                        }

                        return auth()->user()?->canManageProcedures()
                            ? $query->where('category', $category)
                            : $query->whereHas('currentRevision', fn (Builder $revisionQuery): Builder => $revisionQuery->where('category', $category));
                    }),
                SelectFilter::make('plant')
                    ->label('Applies to plant')
                    ->options(StandardOperatingProcedure::plantOptions())
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
                    ->visible(fn (): bool => auth()->user()?->canManageProcedures() ?? false),
                SelectFilter::make('position_id')
                    ->label('Applies to position')
                    ->options(fn (): array => Position::query()->orderBy('display_name')->pluck('display_name', 'id')->all())
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $positionId = $data['value'] ?? null;

                        return $query->when(filled($positionId), fn (Builder $positionQuery): Builder => $positionQuery
                            ->where(function (Builder $audienceQuery) use ($positionId): void {
                                $audienceQuery
                                    ->where('audience', StandardOperatingProcedure::AUDIENCE_ALL_EMPLOYEES)
                                    ->orWhere(function (Builder $selectedPositionQuery) use ($positionId): void {
                                        $selectedPositionQuery
                                            ->where('audience', StandardOperatingProcedure::AUDIENCE_SELECTED_POSITIONS)
                                            ->whereHas('positions', fn (Builder $relationQuery): Builder => $relationQuery->whereKey($positionId));
                                    });
                            }));
                    })
                    ->visible(fn (): bool => auth()->user()?->canManageProcedures() ?? false),
                SelectFilter::make('audience')
                    ->options(StandardOperatingProcedure::audienceOptions())
                    ->visible(fn (): bool => auth()->user()?->canManageProcedures() ?? false),
                SelectFilter::make('library_status')
                    ->label('Document status')
                    ->options([
                        'draft' => 'Draft only',
                        'published' => 'Published',
                        'archived' => 'Retired',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'draft' => $query->whereNull('current_revision_id')->whereNull('archived_at'),
                            'published' => $query->whereNotNull('current_revision_id')->whereNull('archived_at'),
                            'archived' => $query->whereNotNull('archived_at'),
                            default => $query,
                        };
                    })
                    ->visible(fn (): bool => auth()->user()?->canManageProcedures() ?? false),
                SelectFilter::make('review_status')
                    ->label('Review status')
                    ->options([
                        'overdue' => 'Overdue',
                        'due_soon' => 'Due in 30 days',
                        'not_scheduled' => 'Not scheduled',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'overdue' => $query->whereHas('currentRevision', fn (Builder $revisionQuery): Builder => $revisionQuery->whereDate('review_due_date', '<', today())),
                            'due_soon' => $query->whereHas('currentRevision', fn (Builder $revisionQuery): Builder => $revisionQuery->whereBetween('review_due_date', [today(), today()->addDays(30)])),
                            'not_scheduled' => $query->whereHas('currentRevision', fn (Builder $revisionQuery): Builder => $revisionQuery->whereNull('review_due_date')),
                            default => $query,
                        };
                    })
                    ->visible(fn (): bool => auth()->user()?->canManageProcedures() ?? false),
                TernaryFilter::make('public_qr_enabled')
                    ->label('Public QR access')
                    ->visible(fn (): bool => auth()->user()?->canManageProcedures() ?? false),
            ])
            ->filtersFormColumns(2)
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->paginationPageOptions([18, 36, 72])
            ->defaultPaginationPageOption(18)
            ->emptyStateHeading('No policies or procedures available')
            ->emptyStateDescription(fn (): string => auth()->user()?->canManageProcedures()
                ? 'Create the first policy or standard operating procedure for your team.'
                : 'No published policies or procedures currently apply to you.');
    }

    public static function libraryCategory(StandardOperatingProcedure $record): string
    {
        return auth()->user()?->canManageProcedures()
            ? $record->category
            : ($record->currentRevision?->category ?? $record->category);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visibleTo(auth()->user())
            ->with(['currentRevision', 'positions']);
    }

    public static function getGlobalSearchResults(string $search): Collection
    {
        $query = static::getGlobalSearchEloquentQuery();
        $terms = str($search)->squish()->lower()->explode(' ')->filter();

        foreach ($terms as $word) {
            $term = '%'.addcslashes((string) $word, '%_\\').'%';

            if (auth()->user()?->canManageProcedures()) {
                $query->where(function (Builder $searchQuery) use ($term): void {
                    $searchQuery
                        ->whereRaw('LOWER(code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(title) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(summary) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(category) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(document_type) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(CAST(draft_content AS TEXT)) LIKE ?', [$term])
                        ->orWhereHas('currentRevision', fn (Builder $revisionQuery): Builder => $revisionQuery
                            ->whereRaw('LOWER(code) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(title) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(summary) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(category) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(document_type) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(CAST(content AS TEXT)) LIKE ?', [$term]));
                });

                continue;
            }

            $query->whereHas('currentRevision', fn (Builder $revisionQuery): Builder => $revisionQuery
                ->whereRaw('LOWER(code) LIKE ?', [$term])
                ->orWhereRaw('LOWER(title) LIKE ?', [$term])
                ->orWhereRaw('LOWER(summary) LIKE ?', [$term])
                ->orWhereRaw('LOWER(category) LIKE ?', [$term])
                ->orWhereRaw('LOWER(document_type) LIKE ?', [$term])
                ->orWhereRaw('LOWER(CAST(content AS TEXT)) LIKE ?', [$term]));
        }

        return $query
            ->limit(static::getGlobalSearchResultsLimit())
            ->get()
            ->map(function (StandardOperatingProcedure $record): ?GlobalSearchResult {
                $url = static::getGlobalSearchResultUrl($record);

                if (blank($url)) {
                    return null;
                }

                $revision = auth()->user()?->canManageProcedures() ? null : $record->currentRevision;

                return new GlobalSearchResult(
                    title: static::getGlobalSearchResultTitle($record),
                    url: $url,
                    details: [
                        StandardOperatingProcedure::typeOptions()[$revision?->document_type ?? $record->document_type] ?? 'Document' => $revision?->code ?? $record->code,
                        'Topic' => StandardOperatingProcedure::categoryOptions()[$revision?->category ?? $record->category]
                            ?? str($revision?->category ?? $record->category)->headline()->toString(),
                    ],
                );
            })
            ->filter()
            ->values();
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->canViewProcedures()
            && ($user->canManageProcedures() || $user->employee?->is_active));
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof StandardOperatingProcedure) {
            return 'Procedure';
        }

        return auth()->user()?->canManageProcedures()
            ? $record->title
            : ($record->currentRevision?->title ?? $record->title);
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof StandardOperatingProcedure
            && $record->isVisibleTo(auth()->user());
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canManageProcedures() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->canManageProcedures() ?? false;
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
            'index' => ListStandardOperatingProcedures::route('/'),
            'create' => CreateStandardOperatingProcedure::route('/create'),
            'view' => ViewStandardOperatingProcedure::route('/{record}'),
            'edit' => EditStandardOperatingProcedure::route('/{record}/edit'),
        ];
    }
}
