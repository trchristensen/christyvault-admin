<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'supplier_id',
        'status',
        'order_date',
        'expected_delivery_date',
        'received_date',
        'total_amount',
        'notes',
        'created_by_user_id',
        'is_liner_load'
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'expected_delivery_date' => 'datetime',
        'received_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'is_liner_load' => 'boolean'
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_RECEIVED = 'received';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_AWAITING_INVOICE = 'awaiting_invoice';

    public static function getStatuses()
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_RECEIVED,
            self::STATUS_CANCELLED,
            self::STATUS_AWAITING_INVOICE,
        ];
    }

    // Relationships
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->belongsToMany(InventoryItem::class, 'purchase_order_items')
            ->withPivot(['quantity', 'unit_price', 'total_price', 'supplier_sku', 'received_quantity'])
            ->withTimestamps();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function documents()
    {
        return $this->hasMany(PurchaseOrderDocument::class);
    }

    // Scopes
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_SUBMITTED]);
    }

    // Methods
    public function submit()
    {
        $this->update([
            'status' => self::STATUS_SUBMITTED,
            'order_date' => now()
        ]);
    }

    public function markAsReceived()
    {
        $this->update([
            'status' => self::STATUS_RECEIVED,
            'received_date' => now()
        ]);
    }

    public function cancel()
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    public function markAsAwaitingInvoice()
    {
        $this->update(['status' => self::STATUS_AWAITING_INVOICE]);
    }

    public function getTotalItemsAttribute()
    {
        return $this->items->sum('quantity');
    }
}
