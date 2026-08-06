<?php

namespace App\Support\Filament;

use App\Livewire\Profile\AccountInformation;
use App\Livewire\Profile\EmployeeInformation;
use Jeffgreco13\FilamentBreezy\BreezyCore;

final class SharedProfile
{
    public static function make(): BreezyCore
    {
        return BreezyCore::make()
            ->myProfile()
            ->withoutMyProfileComponents(['update_password'])
            ->myProfileComponents([
                'personal_info' => AccountInformation::class,
                'employee_information' => EmployeeInformation::class,
            ]);
    }
}
