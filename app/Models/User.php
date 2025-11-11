<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Althinect\FilamentSpatieRolesPermissions\Concerns\HasSuperAdmin;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;


class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasSuperAdmin;

    public function canAccessPanel(Panel $panel): bool
    {
        var_dump($this->roles->pluck('name'));

        if ($panel->getId() === 'admin') {
            return $this->hasRole(['admin', 'super-admin']);
        } else if ($panel->getId() === 'team') {
            return $this->hasRole(['admin', 'super-admin', 'employee', 'foreman', 'driver']);
        } else if ($panel->getId() === 'operations') {
            return $this->hasRole(['admin', 'super-admin']);
        } else if ($panel->getId() === 'sales') {
            return $this->hasRole(['admin', 'super-admin', 'sales']);
        }

        return false;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function driver()
    {
        return $this->hasOneThrough(Driver::class, Employee::class);
    }

    public function getCalendarFeedUrl(): string
    {
        return url()->signedRoute('calendar.feed', [
            'token' => $this->id
        ], now()->addYears(10)); // Expire in 10 years instead of 24 hours
    }

    public function getLeaveCalendarFeedUrl(): string
    {
        return url()->signedRoute('calendar.leave-feed', [
            'token' => $this->id
        ], now()->addYears(10)); // Expire in 10 years instead of 24 hours
    }
}
