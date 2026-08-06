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
use SpykApp\PasswordlessLogin\Traits\HasMagicLogin;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasSuperAdmin, HasMagicLogin;

    public const TEAM_DELIVERY_SCHEDULE_ROLES = [
        'admin',
        'super-admin',
        'manager',
        'foreman',
        'driver',
        'tulare-driver',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->canAccessPanelById($panel->getId());
    }

    public function canAccessPanelById(string $panelId): bool
    {
        return match ($panelId) {
            'admin', 'operations' => $this->hasRole(['admin', 'super-admin']),
            'team' => $this->hasRole(['admin', 'super-admin', 'manager', 'employee', 'foreman', 'driver', 'tulare-driver']),
            'sales' => $this->hasRole(['admin', 'super-admin', 'sales']),
            'maintenance' => $this->hasRole(['admin', 'super-admin', 'maintenance-manager', 'maintenance-technician']),
            default => false,
        };
    }

    public function canViewTeamDeliverySchedule(): bool
    {
        return $this->hasAnyRole(self::TEAM_DELIVERY_SCHEDULE_ROLES)
            && $this->can('view team delivery schedule');
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
        'team_schedule_delivery_types',
        'team_schedule_days_ahead',
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
            'team_schedule_delivery_types' => 'array',
            'team_schedule_days_ahead' => 'integer',
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

    public function assignedMaintenanceWorkOrders()
    {
        return $this->hasMany(MaintenanceWorkOrder::class, 'assigned_to_user_id');
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
