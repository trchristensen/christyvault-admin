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
use Illuminate\Support\Str;


class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasSuperAdmin;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
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
        'calendar_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'calendar_token',
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

    /**
     * Generate a new calendar token for this user
     */
    public function generateCalendarToken(): string
    {
        $token = Str::random(64);
        $this->update(['calendar_token' => $token]);
        return $token;
    }

    /**
     * Get the calendar token, generating one if it doesn't exist
     */
    public function getCalendarToken(): string
    {
        if (!$this->calendar_token) {
            return $this->generateCalendarToken();
        }
        return $this->calendar_token;
    }

    public function getCalendarFeedUrl(): string
    {
        return route('calendar.feed', [
            'token' => $this->getCalendarToken()
        ]);
    }

    public function getLeaveCalendarFeedUrl(): string
    {
        return route('calendar.leave-feed', [
            'token' => $this->getCalendarToken()
        ]);
    }
}
