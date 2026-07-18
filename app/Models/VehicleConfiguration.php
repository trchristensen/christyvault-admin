<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleConfiguration extends Model
{
    use HasFactory;

    public const CODE_RACK_TRAILER_FORKLIFT_ONBOARD = 'rack_trailer_forklift_onboard';

    public const CODE_BOOM_TRUCK = 'boom_truck';

    public const TYPE_RACK_TRAILER = 'rack_trailer';

    public const TYPE_BOOM_TRUCK = 'boom_truck';

    public const RACK_TRAILER_COUNTS = [8, 10];

    protected $fillable = [
        'code',
        'name',
        'configuration_type',
        'rack_spot_count',
        'max_product_weight_lbs',
        'piggyback_forklift_onboard',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'rack_spot_count' => 'integer',
        'max_product_weight_lbs' => 'decimal:2',
        'piggyback_forklift_onboard' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_RACK_TRAILER => 'Rack trailer',
            self::TYPE_BOOM_TRUCK => 'Boom truck',
        ];
    }

    public static function rackTrailerCountOptions(): array
    {
        return collect(self::RACK_TRAILER_COUNTS)
            ->mapWithKeys(fn (int $count): array => [$count => "{$count} racks"])
            ->all();
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
