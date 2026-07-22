<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoadingProfile extends Model
{
    use HasFactory;

    public const HANDLING_INDIVIDUAL = 'individual';

    public const HANDLING_PALLET = 'pallet';

    public const HANDLING_LOOSE = 'loose';

    public const RACK_STANDARD = 'standard';

    public const RACK_SINGLE = 'single';

    public const RACK_NONE = 'none';

    public const LEVEL_ANY = 'any';

    public const LEVEL_BOTTOM = 'bottom';

    public const PLACEMENT_ONE_PER_LEVEL = 'one_per_level';

    public const PLACEMENT_FULL_TOP_SPLIT_BOTTOM_PAIR = 'full_top_split_bottom_pair';

    protected $fillable = [
        'code',
        'name',
        'handling_method',
        'units_per_pallet',
        'units_per_rack_position',
        'full_load_units',
        'pallet_compatibility_group',
        'rack_requirement',
        'required_rack_level',
        'required_rack_type_id',
        'placement_strategy',
        'is_stackable',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'units_per_pallet' => 'integer',
        'units_per_rack_position' => 'integer',
        'full_load_units' => 'integer',
        'is_stackable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function handlingMethodOptions(): array
    {
        return [
            self::HANDLING_INDIVIDUAL => 'Individual product',
            self::HANDLING_PALLET => 'Palletized',
            self::HANDLING_LOOSE => 'Loose / boxed accessory (no rack space)',
        ];
    }

    public static function rackRequirementOptions(): array
    {
        return [
            self::RACK_STANDARD => 'Standard 2-high or 3-high rack',
            self::RACK_SINGLE => 'Oversized single rack',
            self::RACK_NONE => 'Does not use a rack',
        ];
    }

    public static function requiredRackLevelOptions(): array
    {
        return [
            self::LEVEL_ANY => 'Any allowed level',
            self::LEVEL_BOTTOM => 'Bottom level only',
        ];
    }

    public static function placementStrategyOptions(): array
    {
        return [
            self::PLACEMENT_ONE_PER_LEVEL => 'One complete product per rack level',
            self::PLACEMENT_FULL_TOP_SPLIT_BOTTOM_PAIR => 'Whole products on top; one split product across each bottom pair',
        ];
    }

    public function requiredRackType(): BelongsTo
    {
        return $this->belongsTo(RackType::class, 'required_rack_type_id');
    }

    public function allowedRackTypes(): BelongsToMany
    {
        return $this->belongsToMany(RackType::class, 'loading_profile_rack_type');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isPalletized(): bool
    {
        return $this->handling_method === self::HANDLING_PALLET;
    }

    public function canSharePalletWith(self $other): bool
    {
        return filled($this->pallet_compatibility_group)
            && $this->pallet_compatibility_group === $other->pallet_compatibility_group;
    }
}
