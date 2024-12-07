<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Services\Sage100Service;

class InventoryItem extends Model

{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'category',
        'unit_of_measure',
        'minimum_stock',
        'current_stock',
        'reorder_lead_time',
        'storage_location',
        'qr_code',
        'active',
        'sage_item_code'
    ];

    protected $casts = [
        'minimum_stock' => 'decimal:2',
        'current_stock' => 'decimal:2',
        'active' => 'boolean'
    ];

    // Relationships
    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'inventory_item_suppliers')
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

    public function kanbanCards()
    {
        return $this->hasMany(KanbanCard::class);
    }

    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function purchaseOrders()
    {
        return $this->belongsToMany(PurchaseOrder::class, 'purchase_order_items')
            ->withPivot(['quantity', 'unit_price', 'total_price', 'supplier_sku', 'received_quantity'])
            ->withTimestamps();
    }

    // Helper Methods
    public function preferredSupplier()
    {
        return $this->suppliers()
            ->wherePivot('is_preferred', '=', DB::raw('true'))
            ->first();
    }

    public function needsReorder()
    {
        return $this->current_stock <= $this->minimum_stock;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('current_stock <= minimum_stock');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('current_stock', '<=', 0);
    }

    public function getStockStatusAttribute()
    {
        if ($this->current_stock <= 0) return 'Out of Stock';
        if ($this->current_stock <= $this->minimum_stock) return 'Low Stock';
        return 'In Stock';
    }

    public function syncWithSage(): array
    {
        if (!$this->sage_item_code) {
            return [
                'status' => 'error',
                'message' => 'No Sage item code set for this inventory item',
            ];
        }

        try {
            $sageService = app(Sage100Service::class);

            if (!$sageService->isConfigured() && !$sageService->isTestMode()) {
                return [
                    'status' => 'error',
                    'message' => 'Sage 100 is not properly configured',
                ];
            }

            $result = $sageService->getInventoryLevel($this->sage_item_code);

            if ($result['status'] === 'success') {
                $this->current_stock = $result['quantity'];
                $this->save();

                return [
                    'status' => 'success',
                    'message' => "Updated stock level to {$result['quantity']}",
                ];
            }

            return [
                'status' => 'error',
                'message' => $result['message'] ?? 'Unknown error occurred',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error syncing with Sage: ' . $e->getMessage(),
            ];
        }
    }
}
