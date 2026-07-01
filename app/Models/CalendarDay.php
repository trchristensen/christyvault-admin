<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarDay extends Model
{
    use HasFactory;

    public const TYPE_HOLIDAY = 'holiday';
    public const TYPE_CLOSURE = 'closure';
    public const TYPE_NOTE = 'note';
    public const TYPE_SPECIAL_OPEN_DAY = 'special_open_day';

    protected $fillable = [
        'date',
        'name',
        'type',
        'blocks_delivery',
        'opens_delivery',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'blocks_delivery' => 'boolean',
        'opens_delivery' => 'boolean',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_HOLIDAY => 'Holiday',
            self::TYPE_CLOSURE => 'Company Closure',
            self::TYPE_NOTE => 'Calendar Note',
            self::TYPE_SPECIAL_OPEN_DAY => 'Special Open Day',
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeOptions()[$this->type] ?? str($this->type)->headline()->toString();
    }

}
