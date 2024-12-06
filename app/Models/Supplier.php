<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Supplier extends Model
{
    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'notes',
        'active'
    ];

    protected $casts = [
        'active' => 'boolean'
    ];

    // Relationships
    public function inventoryItems()
    {
        return $this->belongsToMany(InventoryItem::class, 'inventory_item_suppliers')
            ->withPivot([
                'is_preferred',
                'supplier_sku',
                'cost_per_unit',
                'minimum_order_quantity',
                'lead_time_days',
                'last_supplied_at',
                'notes'
            ])
            ->withTimestamps();
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}