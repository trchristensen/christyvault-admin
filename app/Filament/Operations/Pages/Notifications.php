<?php

namespace App\Filament\Operations\Pages;

use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Database\Eloquent\Collection;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class Notifications extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Notifications';
    protected static ?string $title = 'Notifications';
    protected static ?string $slug = 'notifications';

    protected static string $view = 'filament.operations.pages.notifications';




    public function table(Table $table): Table
    {
        return $table
            ->query(
                auth()->user()->notifications()->getQuery()
            )
            ->columns([
                TextColumn::make('data.inventory_item_name')
                    ->label('Item')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('data.bin_number')
                    ->label('Bin')
                    ->searchable(),
                TextColumn::make('data.bin_location')
                    ->label('Location')
                    ->searchable(),
                TextColumn::make('data.scanned_by')
                    ->label('Scanned By')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable()
                    ->description(fn($record) => $record->created_at->diffForHumans()),
                TextColumn::make('read_at')
                    ->label('Status')
                    ->badge()
                    ->color(fn($record) => $record->read_at ? 'success' : 'warning')
                    ->formatStateUsing(fn($record) => $record->read_at ? 'Read' : 'Unread'),
            ])
            ->actions([
                Action::make('view')
                    ->icon('heroicon-m-eye')
                    ->url(fn($record) => $record->data['link'] ?? '#')
                    ->openUrlInNewTab(),
                Action::make('mark_as_read')
                    ->icon('heroicon-m-check')
                    ->hidden(fn($record) => $record->read_at !== null)
                    ->action(function ($record) {
                        $record->markAsRead();
                        FilamentNotification::make()
                            ->success()
                            ->title('Notification marked as read')
                            ->send();
                    }),
                Action::make('delete')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->delete();
                        FilamentNotification::make()
                            ->success()
                            ->title('Notification deleted')
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('mark_as_read')
                    ->label('Mark as Read')
                    ->icon('heroicon-m-check')
                    ->action(function (Collection $records) {
                        $records->each->markAsRead();
                        FilamentNotification::make()
                            ->success()
                            ->title('Selected notifications marked as read')
                            ->send();
                    }),
                BulkAction::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $records->each->delete();
                        FilamentNotification::make()
                            ->success()
                            ->title('Selected notifications deleted')
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
