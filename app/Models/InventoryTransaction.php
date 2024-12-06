<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'transaction_type',
        'quantity',
        'user_id',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'decimal:2'
    ];

    // Transaction type constants
    const TYPE_REORDER = 'reorder';
    const TYPE_RECEIPT = 'receipt';
    const TYPE_CONSUMPTION = 'consumption';

    public static function getTransactionTypes()
    {
        return [
            self::TYPE_REORDER,
            self::TYPE_RECEIPT,
            self::TYPE_CONSUMPTION,
        ];
    }

    // Relationships
    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeOfType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    // Methods
    protected static function booted()
    {
        static::created(function ($transaction) {
            // Update inventory item stock based on transaction type
            $item = $transaction->inventoryItem;
            
            if ($transaction->transaction_type === self::TYPE_RECEIPT) {
                $item->increment('current_stock', $transaction->quantity);
            } elseif ($transaction->transaction_type === self::TYPE_CONSUMPTION) {
                $item->decrement('current_stock', $transaction->quantity);
            }
        });
    }
}