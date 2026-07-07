<?php

namespace App\Filament\Operations\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestNotificationsWidget extends TableWidget
{
    protected static ?int $sort = 2;
    protected int $defaultPaginationPageOption = 5;
    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return auth()->user()
            ->notifications()
            ->latest()
            ->take(3)
            ->getQuery();
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('data.title')
                ->label('Title'),
            TextColumn::make('data.body')
                ->label('Description')
                ->wrap(),
            TextColumn::make('created_at')
                ->label('Time')
                ->dateTime()
                ->description(fn($record) => $record->created_at->diffForHumans()),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }
} 