<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'phone_extension',
        'mobile_phone',
        'title',
        'is_active',
        'contact_type',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getNameWithTitleAttribute(): string
    {
        return "{$this->name}" . ($this->title ? " - {$this->title}" : "");
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'contact_location')
            ->select([
                'locations.id',
                'locations.name',
                'locations.address_line1',
                'locations.city',
                'locations.state',
                'locations.postal_code'
            ])
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function salesVisits(): HasMany
    {
        return $this->hasMany(SalesVisit::class);
    }

    public function contactTypes(): BelongsToMany
    {
        return $this->belongsToMany(ContactType::class);
    }
}
