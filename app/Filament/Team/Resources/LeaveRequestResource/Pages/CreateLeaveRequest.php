<?php

namespace App\Filament\Team\Resources\LeaveRequestResource\Pages;

use App\Filament\Team\Resources\LeaveRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLeaveRequest extends CreateRecord
{
    protected static string $resource = LeaveRequestResource::class;
}
