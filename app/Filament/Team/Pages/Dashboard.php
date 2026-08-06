<?php

namespace App\Filament\Team\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected ?string $subheading = 'Company dates, time off, and work updates in one place.';

    public function getHeading(): string
    {
        $name = trim((string) auth()->user()?->name);
        $firstName = str($name)->before(' ')->toString();

        return filled($firstName) ? "Welcome, {$firstName}" : 'Welcome';
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
