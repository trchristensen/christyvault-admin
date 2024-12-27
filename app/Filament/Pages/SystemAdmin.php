<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use App\Models\Backup;

class SystemAdmin extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'System Admin';
    protected static ?string $title = 'System Administration';
    protected static ?int $navigationSort = 100;
    protected static ?string $navigationGroup = 'System';
    protected static string $view = 'filament.pages.system-admin';

    public static function canAccess(): bool
    {
        return auth()->user()->email === 'tchristensen@christyvault.com';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backup')
                ->label('Run Backup')
                ->action(function () {
                    try {
                        Artisan::call('backup:run');
                        Notification::make()
                            ->title('Backup started')
                            ->success()
                            ->body('The backup process has been initiated.')
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Backup failed')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                })
                ->requiresConfirmation(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Backup::query())
            ->columns([
                TextColumn::make('filename')
                    ->label('File')
                    ->searchable(),
                TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn($state) => number_format($state / 1048576, 2) . ' MB'),
                TextColumn::make('date')
                    ->label('Date')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->defaultSort('date', 'desc');
    }
}
