<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceVendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'contact_person', 'phone', 'email', 'address', 'services_provided', 'notes', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class, 'maintenance_vendor_id');
    }

    public function fleetPlans(): HasMany
    {
        return $this->hasMany(MaintenanceFleetPlan::class, 'maintenance_vendor_id');
    }

    public function snapshot(): array
    {
        return [
            'service_provider' => $this->name,
            'service_contact_name' => $this->contact_person,
            'service_phone' => $this->phone,
        ];
    }
}
