<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Arr;

class SharedProfileUpdater
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function updateAccount(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'],
        ]);

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateEmployeeContact(User $user, array $data): Employee
    {
        $employee = $user->employee;

        abort_unless($employee, 403);

        $employee->update(Arr::only($data, ['phone', 'address']));

        return $employee->fresh();
    }
}
