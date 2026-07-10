<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class OrderDeliveryPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'uploaded_by_user_id',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size',
        'notes',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function getUrlAttribute(): string
    {
        $disk = Storage::disk($this->disk ?: 'r2');

        try {
            return $disk->temporaryUrl($this->path, now()->addMinutes(30));
        } catch (\Throwable) {
            return $disk->url($this->path);
        }
    }
}
