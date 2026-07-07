<?php

namespace App\Filament\Team\Resources\LeaveRequestResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Team\Resources\LeaveRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLeaveRequests extends ListRecords
{
    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
