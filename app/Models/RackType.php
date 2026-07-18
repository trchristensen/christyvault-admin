<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RackType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'level_count',
        'pallet_capable_levels',
        'pallets_per_capable_level',
        'supports_standard_boxes',
        'supports_oversized_boxes',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'level_count' => 'integer',
        'pallet_capable_levels' => 'integer',
        'pallets_per_capable_level' => 'integer',
        'supports_standard_boxes' => 'boolean',
        'supports_oversized_boxes' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function palletCapacity(): int
    {
        return $this->pallet_capable_levels * $this->pallets_per_capable_level;
    }
}
