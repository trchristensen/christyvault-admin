<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class TestCalendar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.test-calendar';

    protected static ?string $navigationGroup = 'Testing';
    protected static ?string $title = 'Test Calendar';
}
