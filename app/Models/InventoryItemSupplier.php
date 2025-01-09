<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItemSupplier extends Model
{
    protected $table = 'inventory_item_suppliers';

    protected $fillable = [
        'inventory_item_id',
        'supplier_id',
        'is_preferred',
        'supplier_sku',
        'minimum_order_quantity',
        'lead_time_days',
        'last_supplied_at',
        'notes'
    ];

    protected $casts = [
        'is_preferred' => 'boolean',
        'minimum_order_quantity' => 'decimal:2',
        'last_supplied_at' => 'datetime'
    ];

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
