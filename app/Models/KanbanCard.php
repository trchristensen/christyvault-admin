<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KanbanCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'bin_number',
        'bin_location',
        'reorder_point',
        'status',
        'last_scanned_at',
        'scanned_by_user_id'
    ];

    protected $casts = [
        'last_scanned_at' => 'datetime',
        'reorder_point' => 'decimal:2'
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_PENDING_ORDER = 'pending_order';
    const STATUS_ORDERED = 'ordered';

    public static function getStatuses()
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_PENDING_ORDER,
            self::STATUS_ORDERED,
        ];
    }

    // Relationships
    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function scannedBy()
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }

    // Scopes
    public function scopePendingOrder($query)
    {
        return $query->where('status', self::STATUS_PENDING_ORDER);
    }

    // Methods
    public function markAsScanned()
    {
        $this->update([
            'status' => self::STATUS_PENDING_ORDER,
            'last_scanned_at' => now(),
            'scanned_by_user_id' => auth()->id()
        ]);
    }

    public function canBeScanned(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
