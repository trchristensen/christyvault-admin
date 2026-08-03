<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceWorkOrderLaborEntry extends Model
{
    use HasFactory;

    protected $fillable = ['work_order_id', 'user_id', 'started_at', 'ended_at', 'minutes', 'notes'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceWorkOrder::class, 'work_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stop(): void
    {
        $endedAt = now();
        $this->update(['ended_at' => $endedAt, 'minutes' => $this->started_at->diffInMinutes($endedAt)]);
    }
}
