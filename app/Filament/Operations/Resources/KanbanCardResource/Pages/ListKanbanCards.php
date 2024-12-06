<?php

namespace App\Filament\Operations\Resources\KanbanCardResource\Pages;

use App\Filament\Operations\Resources\KanbanCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKanbanCards extends ListRecords
{
    protected static string $resource = KanbanCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
