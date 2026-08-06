<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'email',
        'address',
        'phone',
        'is_active',
        'christy_location',
        'hire_date',
        'birth_date',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'hire_date' => 'date',
        'birth_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (Employee $employee): void {
            $structuredFields = ['first_name', 'middle_name', 'last_name', 'suffix'];

            if ($employee->isDirty('name') && ! $employee->isDirty($structuredFields)) {
                $employee->forceFill(static::splitName($employee->name));
            }

            $fullName = $employee->formattedName();

            if ($fullName !== '') {
                $employee->name = $fullName;
            }
        });
    }

    /**
     * @return array{first_name: ?string, middle_name: ?string, last_name: ?string, suffix: ?string}
     */
    public static function splitName(?string $name): array
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', trim((string) $name)));
        $parts = $normalized === '' ? [] : explode(' ', $normalized);
        $suffix = null;

        if (count($parts) > 1 && in_array(strtolower((string) end($parts)), [
            'jr', 'jr.', 'sr', 'sr.', 'ii', 'iii', 'iv', 'v',
        ], true)) {
            $suffix = array_pop($parts);
        }

        $firstName = array_shift($parts);
        $lastName = $parts === [] ? null : array_pop($parts);

        return [
            'first_name' => $firstName ?: null,
            'middle_name' => $parts === [] ? null : implode(' ', $parts),
            'last_name' => $lastName ?: null,
            'suffix' => $suffix,
        ];
    }

    public function formattedName(): string
    {
        return collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ])->filter(fn ($part): bool => filled($part))
            ->map(fn ($part): string => trim((string) $part))
            ->implode(' ');
    }

    // ... other properties and methods ...

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function driver()
    {
        return $this->hasOne(Driver::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'driver_id');
    }

    public function positions()
    {
        return $this->belongsToMany(Position::class)->withTimestamps();
    }

    public function isDriver()
    {
        return $this->positions()->where('name', 'driver')->exists();
    }

    public function hasPosition(string $position): bool
    {
        return $this->positions()->where('name', $position)->exists();
    }

    public function christyVaultLocation()
    {
        return Location::where('location_type', 'christy_vault')
            ->where('name', 'like', '%'.$this->christy_location.'%');
    }
}
