<?php

namespace App\Filament\Resources\LocationResource\Pages;

use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use App\Filament\Resources\LocationResource;
use Filament\Actions;
use Filament\Infolists\Components\ViewEntry;
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
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
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
