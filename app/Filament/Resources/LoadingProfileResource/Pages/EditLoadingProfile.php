<?php

namespace App\Filament\Resources\LoadingProfileResource\Pages;

use App\Filament\Resources\LoadingProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLoadingProfile extends EditRecord
{
    protected static string $resource = LoadingProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
