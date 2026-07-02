<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use Filament\Actions;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewLocation extends ViewRecord
{
    protected static string $resource = LocationResource::class;

    public function getTitle(): string | Htmlable
    {
        return $this->getRecord()->name;
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                ViewEntry::make('profile')
                    ->hiddenLabel()
                    ->view('filament.resources.location-resource.entries.profile')
                    ->columnSpanFull(),
            ]);
    }
}
