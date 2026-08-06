<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeaveRequestResource\Pages\CreateLeaveRequest;
use App\Filament\Resources\LeaveRequestResource\Pages\EditLeaveRequest;
use App\Filament\Resources\LeaveRequestResource\Pages\ListLeaveRequests;
use App\Models\LeaveRequest;
use App\Rules\WeekdayDateRange;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;

class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Directories';

    protected static ?string $navigationParentItem = 'People';

    public static function canAccess(): bool
    {
        return auth()->user()?->canViewTeamTimeOffOverview() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pendingRequests = static::getModel()::query()
            ->visibleTo(auth()->user())
            ->where('status', 'pending')
            ->count();

        return $pendingRequests > 0 ? (string) $pendingRequests : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pending time-off requests';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_id')
                    ->relationship(
                        name: 'employee',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            $user = auth()->user();

                            if ($user?->canManageAllTimeOffRequests()) {
                                return $query;
                            }

                            return $query->where('christy_location', $user?->employee?->christy_location);
                        },
                    )
                    ->required(),
                Select::make('type')
                    ->options([
                        'sick' => 'Sick Leave',
                        'vacation' => 'Vacation',
                        'unpaid' => 'Unpaid Leave',
                    ])
                    ->required(),
                ToggleButtons::make('duration')
                    ->label('How much time?')
                    ->options([
                        'full_day' => 'Full day(s)',
                        'specific_hours' => 'Specific hours',
                    ])
                    ->icons([
                        'full_day' => 'heroicon-o-calendar-days',
                        'specific_hours' => 'heroicon-o-clock',
                    ])
                    ->default('full_day')
                    ->grouped()
                    ->live()
                    ->dehydrated(false)
                    ->required()
                    ->columnSpanFull(),
                DateRangePicker::make('date_range')
                    ->label('Dates')
                    ->format(LeaveRequest::DATE_RANGE_FORMAT)
                    ->rangeSeparator(LeaveRequest::DATE_RANGE_SEPARATOR)
                    ->disableRanges()
                    ->autoApply()
                    ->firstDayOfWeek(0)
                    ->placeholder('Select start and end dates')
                    ->helperText('Choose the first and last weekday. Weekend dates cannot be selected.')
                    ->rules([new WeekdayDateRange(allowPast: true)])
                    ->required(fn (Get $get): bool => $get('duration') === 'full_day')
                    ->hidden(fn (Get $get): bool => $get('duration') !== 'full_day')
                    ->dehydratedWhenHidden(false)
                    ->columnSpanFull(),
                DateRangePicker::make('date_time_range')
                    ->label('Dates and times')
                    ->format(LeaveRequest::DATE_TIME_RANGE_FORMAT)
                    ->rangeSeparator(LeaveRequest::DATE_RANGE_SEPARATOR)
                    ->disableRanges()
                    ->timePicker()
                    ->timePickerIncrement(30)
                    ->firstDayOfWeek(0)
                    ->placeholder('Select start and end times')
                    ->helperText('Choose the exact weekday and time the absence begins and ends.')
                    ->rules([new WeekdayDateRange(includesTime: true, allowPast: true)])
                    ->required(fn (Get $get): bool => $get('duration') === 'specific_hours')
                    ->hidden(fn (Get $get): bool => $get('duration') !== 'specific_hours')
                    ->dehydratedWhenHidden(false)
                    ->columnSpanFull(),
                Textarea::make('reason')
                    ->columnSpanFull(),
                Select::make('status')
                    ->default('pending')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->required(),
                TextInput::make('reviewed_by')
                    ->numeric()
                    ->disabled(),
                Textarea::make('review_notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof LeaveRequest
            && auth()->user()
            && $record->isVisibleTo(auth()->user());
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user?->canManageAllTimeOffRequests()
            || ($user?->canManagePlantTimeOffRequests() && filled($user->employee?->christy_location));
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof LeaveRequest
            && auth()->user()
            && $record->isManageableBy(auth()->user());
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->canManagePlantTimeOffRequests() ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('start_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('reviewer.name')
                    // ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeaveRequests::route('/'),
            'create' => CreateLeaveRequest::route('/create'),
            'edit' => EditLeaveRequest::route('/{record}/edit'),
        ];
    }
}
