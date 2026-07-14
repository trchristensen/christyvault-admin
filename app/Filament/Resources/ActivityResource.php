<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages\ListActivities;
use App\Filament\Resources\ActivityResource\Pages\ViewActivity;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Activity Log';

    protected static ?string $modelLabel = 'activity';

    protected static ?string $pluralModelLabel = 'activity log';

    protected static ?int $navigationSort = 90;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y g:i:s A')
                    ->sinceTooltip()
                    ->sortable(),
                TextColumn::make('causer_display')
                    ->label('User')
                    ->state(fn (Activity $record): string => static::causerLabel($record))
                    ->wrap(),
                TextColumn::make('event')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? ucfirst($state) : 'Activity')
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'restored' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('log_name')
                    ->label('Category')
                    ->badge()
                    ->placeholder('General')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject_display')
                    ->label('Record')
                    ->state(fn (Activity $record): string => static::subjectLabel($record))
                    ->wrap(),
                TextColumn::make('description')
                    ->searchable()
                    ->wrap()
                    ->limit(80),
                TextColumn::make('changed_attributes')
                    ->label('Changed')
                    ->state(fn (Activity $record): string => static::changedAttributes($record))
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Category')
                    ->options(fn (): array => Activity::query()
                        ->whereNotNull('log_name')
                        ->distinct()
                        ->orderBy('log_name')
                        ->pluck('log_name', 'log_name')
                        ->all()),
                SelectFilter::make('event')
                    ->options(fn (): array => Activity::query()
                        ->whereNotNull('event')
                        ->distinct()
                        ->orderBy('event')
                        ->pluck('event', 'event')
                        ->mapWithKeys(fn (string $event): array => [$event => ucfirst($event)])
                        ->all()),
                SelectFilter::make('subject_type')
                    ->label('Record Type')
                    ->options(fn (): array => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->orderBy('subject_type')
                        ->pluck('subject_type', 'subject_type')
                        ->mapWithKeys(fn (string $type): array => [$type => class_basename($type)])
                        ->all()),
                Filter::make('user')
                    ->schema([
                        Select::make('user_id')
                            ->label('User')
                            ->options(fn (): array => User::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['user_id'] ?? null,
                            fn (Builder $query, int|string $userId): Builder => $query
                                ->where('causer_type', User::class)
                                ->where('causer_id', $userId),
                        )),
                Filter::make('created_at')
                    ->label('Date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date),
                        )),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([25, 50, 100]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Activity')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('When')
                                    ->dateTime('M j, Y g:i:s A'),
                                TextEntry::make('causer_display')
                                    ->label('User')
                                    ->state(fn (Activity $record): string => static::causerLabel($record)),
                                TextEntry::make('event')
                                    ->badge()
                                    ->placeholder('Activity'),
                                TextEntry::make('log_name')
                                    ->label('Category')
                                    ->badge()
                                    ->placeholder('General'),
                                TextEntry::make('subject_display')
                                    ->label('Record')
                                    ->state(fn (Activity $record): string => static::subjectLabel($record)),
                                TextEntry::make('batch_uuid')
                                    ->label('Batch')
                                    ->placeholder('—')
                                    ->copyable(),
                            ]),
                        TextEntry::make('description')
                            ->columnSpanFull(),
                    ]),
                Section::make('Changes')
                    ->description('The stored values before and after this activity.')
                    ->schema([
                        KeyValueEntry::make('old_values')
                            ->label('Before')
                            ->state(fn (Activity $record): array => static::displayValues(static::propertyValues($record, 'old')))
                            ->keyLabel('Field')
                            ->valueLabel('Before')
                            ->placeholder('No previous values were recorded.'),
                        KeyValueEntry::make('new_values')
                            ->label('After')
                            ->state(fn (Activity $record): array => static::displayValues(static::propertyValues($record, 'attributes')))
                            ->keyLabel('Field')
                            ->valueLabel('After')
                            ->placeholder('No new values were recorded.'),
                    ])
                    ->columns(2)
                    ->visible(fn (Activity $record): bool => static::hasChanges($record)),
                Section::make('Additional Context')
                    ->schema([
                        KeyValueEntry::make('additional_properties')
                            ->hiddenLabel()
                            ->state(fn (Activity $record): array => static::displayValues(static::additionalProperties($record)))
                            ->keyLabel('Property')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->visible(fn (Activity $record): bool => static::additionalProperties($record) !== []),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('causer');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function causerLabel(Activity $activity): string
    {
        if ($activity->causer instanceof User) {
            return $activity->causer->name ?: $activity->causer->email;
        }

        if (blank($activity->causer_type) && blank($activity->causer_id)) {
            return 'System';
        }

        return sprintf(
            'Deleted %s #%s',
            class_basename((string) $activity->causer_type),
            $activity->causer_id ?: '?',
        );
    }

    public static function subjectLabel(Activity $activity): string
    {
        if (blank($activity->subject_type) && blank($activity->subject_id)) {
            return '—';
        }

        return sprintf(
            '%s #%s',
            class_basename((string) $activity->subject_type),
            $activity->subject_id ?: '?',
        );
    }

    public static function changedAttributes(Activity $activity): string
    {
        return implode(', ', array_keys(static::propertyValues($activity, 'attributes')));
    }

    public static function hasChanges(Activity $activity): bool
    {
        return static::propertyValues($activity, 'old') !== []
            || static::propertyValues($activity, 'attributes') !== [];
    }

    public static function propertyValues(Activity $activity, string $key): array
    {
        $properties = $activity->properties;

        if ($properties instanceof Collection) {
            $properties = $properties->all();
        }

        $values = is_array($properties) ? ($properties[$key] ?? []) : [];

        return is_array($values) ? $values : [];
    }

    public static function additionalProperties(Activity $activity): array
    {
        $properties = $activity->properties;

        if ($properties instanceof Collection) {
            $properties = $properties->all();
        }

        if (! is_array($properties)) {
            return [];
        }

        unset($properties['old'], $properties['attributes']);

        return $properties;
    }

    public static function displayValues(array $values): array
    {
        return collect($values)
            ->map(function (mixed $value): string {
                if ($value === null) {
                    return 'null';
                }

                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }

                if (is_scalar($value)) {
                    return (string) $value;
                }

                return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[unavailable]';
            })
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'view' => ViewActivity::route('/{record}'),
        ];
    }
}
