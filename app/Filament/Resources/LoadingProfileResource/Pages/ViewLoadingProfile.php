<?php

namespace App\Filament\Resources\LoadingProfileResource\Pages;

use App\Filament\Resources\LoadingProfileResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLoadingProfile extends ViewRecord
{
    protected static string $resource = LoadingProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
