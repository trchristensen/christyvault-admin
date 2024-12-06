<?php

namespace App\Filament\Operations\Resources\KanbanCardResource\Pages;

use App\Filament\Operations\Resources\KanbanCardResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateKanbanCard extends CreateRecord
{
    protected static string $resource = KanbanCardResource::class;
}
