<?php

namespace App\Filament\Team\Resources\LeaveRequestResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Team\Resources\LeaveRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLeaveRequest extends EditRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
