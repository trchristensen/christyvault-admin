<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Filament\Notifications\Notification;

class SystemAdmin extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'System Admin';
    protected static ?string $title = 'System Administration';
    protected static ?int $navigationSort = 100;
    protected static ?string $navigationGroup = 'System';

    // Define the view property correctly
    protected static string $view = 'filament.pages.system-admin';

    // Only show this page to specific users
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
                        $output = Artisan::output();

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

    protected function getViewData(): array
    {
        $disk = Storage::disk('local');
        $files = $disk->files('Laravel');
        $backups = [];

        foreach ($files as $file) {
            if (str_ends_with($file, '.zip')) {
                $backups[] = [
                    'file' => $file,
                    'size' => $disk->size($file),
                    'date' => Carbon::createFromTimestamp($disk->lastModified($file)),
                ];
            }
        }

        return [
            'backups' => $backups,
        ];
    }
}
