<?php

namespace App\Filament\Team\Resources;

use App\Filament\Team\Resources\TrainingAssignmentResource\Pages\ListTrainingAssignments;
use App\Filament\Team\Resources\TrainingAssignmentResource\Pages\ViewTrainingAssignment;
use App\Models\TrainingAssignment;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TrainingAssignmentResource extends Resource
{
    protected static ?string $model = TrainingAssignment::class;

    protected static ?string $slug = 'training';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'Employee Resources';

    protected static ?string $navigationLabel = 'Training';

    protected static ?string $modelLabel = 'training assignment';

    protected static ?string $pluralModelLabel = 'Training';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('program.title')
                    ->label('Training')
                    ->description(fn (TrainingAssignment $record): string => 'Program version '.$record->program_version)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->canManageTraining() ?? false),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => TrainingAssignment::statusOptions()[$state] ?? str($state)->headline())
                    ->color(fn (string $state): string => match ($state) {
                        TrainingAssignment::STATUS_COMPLETED => 'success',
                        TrainingAssignment::STATUS_IN_PROGRESS => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('due_date')
                    ->date('M j, Y')
                    ->placeholder('No due date')
                    ->color(fn (TrainingAssignment $record): ?string => $record->status !== TrainingAssignment::STATUS_COMPLETED
                        && $record->due_date?->isPast() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('latest_score')
                    ->label('Latest score')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : "{$state}%"),
                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->recordUrl(fn (TrainingAssignment $record): string => static::getUrl('view', ['record' => $record]))
            ->defaultSort('assigned_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(TrainingAssignment::statusOptions()),
                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => auth()->user()?->canManageTraining() ?? false),
            ])
            ->emptyStateHeading('No training assignments')
            ->emptyStateDescription(fn (): string => auth()->user()?->canManageTraining()
                ? 'Assign a published training program to one or more employees.'
                : 'You do not currently have any assigned training.');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visibleTo(auth()->user())
            ->with(['program', 'employee.user', 'attempts']);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->canViewTraining()
            && ($user->canManageTraining() || $user->employee?->is_active));
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof TrainingAssignment && $record->isVisibleTo(auth()->user());
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

    public static function getPages(): array
    {
        return [
            'index' => ListTrainingAssignments::route('/'),
            'view' => ViewTrainingAssignment::route('/{record}'),
        ];
    }
}
