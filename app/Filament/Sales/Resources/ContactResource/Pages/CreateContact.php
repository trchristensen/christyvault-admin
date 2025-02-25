<?php

namespace App\Filament\Sales\Resources\ContactResource\Pages;

use App\Filament\Sales\Resources\ContactResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateContact extends CreateRecord
{
    protected static string $resource = ContactResource::class;
}
