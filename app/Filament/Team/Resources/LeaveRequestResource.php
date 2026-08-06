<?php

namespace App\Filament\Team\Resources;

use App\Filament\Team\Resources\LeaveRequestResource\Pages\CreateLeaveRequest;
use App\Filament\Team\Resources\LeaveRequestResource\Pages\EditLeaveRequest;
use App\Filament\Team\Resources\LeaveRequestResource\Pages\ListLeaveRequests;
use App\Models\LeaveRequest;
use App\Rules\WeekdayDateRange;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;

class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static ?string $slug = 'time-off-requests';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Time Off Requests';

    protected static ?string $modelLabel = 'Time Off Request';

    protected static ?string $pluralModelLabel = 'Time Off Requests';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request Details')
                    ->description('Submit the dates you need off. The office will review your request.')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('type')
                            ->options([
                                'vacation' => 'Vacation',
                                'sick' => 'Sick Leave',
                                'unpaid' => 'Unpaid Leave',
                                'other' => 'Other',
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
                            ->required(),
                        DateRangePicker::make('date_range')
                            ->label('Dates')
                            ->format(LeaveRequest::DATE_RANGE_FORMAT)
                            ->rangeSeparator(LeaveRequest::DATE_RANGE_SEPARATOR)
                            ->minDate(today()->toDateString())
                            ->disableRanges()
                            ->autoApply()
                            ->firstDayOfWeek(0)
                            ->placeholder('Select start and end dates')
                            ->helperText('Choose the first and last weekday you need off. Weekend dates cannot be selected.')
                            ->rules([new WeekdayDateRange])
                            ->required(fn (Get $get): bool => $get('duration') === 'full_day')
                            ->hidden(fn (Get $get): bool => $get('duration') !== 'full_day')
                            ->dehydratedWhenHidden(false),
                        DateRangePicker::make('date_time_range')
                            ->label('Dates and times')
                            ->format(LeaveRequest::DATE_TIME_RANGE_FORMAT)
                            ->rangeSeparator(LeaveRequest::DATE_RANGE_SEPARATOR)
                            ->minDate(today()->toDateString())
                            ->disableRanges()
                            ->timePicker()
                            ->timePickerIncrement(30)
                            ->firstDayOfWeek(0)
                            ->placeholder('Select start and end times')
                            ->helperText('Choose the exact weekday and time your absence begins and ends.')
                            ->rules([new WeekdayDateRange(includesTime: true)])
                            ->required(fn (Get $get): bool => $get('duration') === 'specific_hours')
                            ->hidden(fn (Get $get): bool => $get('duration') !== 'specific_hours')
                            ->dehydratedWhenHidden(false),
                        Textarea::make('reason')
                            ->label('Notes')
                            ->helperText('Optional. Add anything the office should know when reviewing the request.')
                            ->rows(4)
                            ->maxLength(1000),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->formatStateUsing(fn (string $state): string => str($state)->headline())
                    ->badge(),
                TextColumn::make('date_range')
                    ->label('Dates')
                    ->state(fn (LeaveRequest $record): string => $record->dateSummary())
                    ->wrap(),
                TextColumn::make('status')
                    ->formatStateUsing(fn (string $state): string => str($state)->headline())
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    })
                    ->badge(),
                TextColumn::make('reason')
                    ->label('Notes')
                    ->placeholder('—')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->label('Cancel request')
                    ->modalHeading('Cancel this time-off request?'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $employeeId = auth()->user()?->employee?->getKey();

        return parent::getEloquentQuery()
            ->when(
                $employeeId,
                fn (Builder $query): Builder => $query->where('employee_id', $employeeId),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            );
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->employee !== null;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->employee !== null;
    }

    public static function canEdit(Model $record): bool
    {
        return static::ownsPendingRequest($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::ownsPendingRequest($record);
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    protected static function ownsPendingRequest(Model $record): bool
    {
        return $record instanceof LeaveRequest
            && $record->status === 'pending'
            && (int) $record->employee_id === (int) auth()->user()?->employee?->getKey();
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
