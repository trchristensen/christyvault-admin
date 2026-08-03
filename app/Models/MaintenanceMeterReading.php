<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceMeterReading extends Model
{
    use HasFactory;

    protected $fillable = ['asset_id', 'recorded_by_user_id', 'reading', 'recorded_at', 'source', 'notes'];

    protected function casts(): array
    {
        return ['reading' => 'decimal:2', 'recorded_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAsset::class, 'asset_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
