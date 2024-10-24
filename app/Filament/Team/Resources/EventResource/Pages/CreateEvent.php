<?php

namespace App\Filament\Team\Resources\EventResource\Pages;

use App\Filament\Team\Resources\EventResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;
}
