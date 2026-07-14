<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class OrderDeliveryPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'uploaded_by_user_id',
        'disk',
        'path',
        'thumbnail_path',
        'display_path',
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
        return $this->temporaryUrlFor($this->path);
    }

    public function getThumbnailUrlAttribute(): string
    {
        return $this->temporaryUrlFor($this->thumbnail_path ?: $this->path);
    }

    public function getDisplayUrlAttribute(): string
    {
        return $this->temporaryUrlFor($this->display_path ?: $this->path);
    }

    protected function temporaryUrlFor(string $path): string
    {
        $disk = Storage::disk($this->disk ?: 'r2');

        return Cache::remember(
            'delivery-photo-url:'.hash('sha256', ($this->disk ?: 'r2').'|'.$path),
            now()->addMinutes(20),
            function () use ($disk, $path): string {
                try {
                    return $disk->temporaryUrl($path, now()->addMinutes(30));
                } catch (\Throwable) {
                    return $disk->url($path);
                }
            },
        );
    }
}
