<?php

namespace App\Filament\Resources\LoadingProfileResource\Pages;

use App\Filament\Resources\LoadingProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLoadingProfiles extends ListRecords
{
    protected static string $resource = LoadingProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
