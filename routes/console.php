<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('01:30')
    ->onFailure(function () {
        Log::error('Backup failed');
    })
    ->onSuccess(function () {
        Log::info('Backup successful');
    });

Schedule::command('locations:geocode --limit=10')
    ->hourlyAt(5)
    ->timezone(config('app.timezone', 'America/Los_Angeles'))
    ->withoutOverlapping();

Schedule::command('locations:update-plant-distances --limit=10')
    ->hourlyAt(10)
    ->timezone(config('app.timezone', 'America/Los_Angeles'))
    ->withoutOverlapping()
    ->when(fn(): bool => filled(config('services.openrouteservice.api_key')));
