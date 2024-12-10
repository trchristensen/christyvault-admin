<?php

namespace App\Filament\Operations\Widgets;

use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestNotificationsWidget extends TableWidget
{
    protected static ?int $sort = 2;
    protected int $defaultPaginationPageOption = 5;

    protected function getTableQuery(): Builder
    {
        return auth()->user()
            ->notifications()
            ->latest()
            ->take(5)
            ->getQuery();
    }

    protected function getTableColumns(): array
    {
        return [
            \Filament\Tables\Columns\TextColumn::make('data.inventory_item_name')
                ->label('Item'),
            \Filament\Tables\Columns\TextColumn::make('data.bin_number')
                ->label('Bin'),
            \Filament\Tables\Columns\TextColumn::make('created_at')
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