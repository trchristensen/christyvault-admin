<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'latitude',
        'longitude',
        'location_type',
        'notes',
        'preferred_delivery_contact_id',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function getCoordinatesAttribute(): ?string
    {
        if ($this->latitude && $this->longitude) {
            return "{$this->latitude}, {$this->longitude}";
        }
        return null;
    }

    public function hasCoordinates(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    public function trips(): MorphToMany
    {
        return $this->morphedByMany(Trip::class, 'locationable')
            ->withPivot('type', 'sequence')
            ->withTimestamps();
    }

    public function customers(): MorphToMany
    {
        return $this->morphedByMany(Customer::class, 'locationable')
            ->withPivot('type', 'sequence')
            ->withTimestamps();
    }

    // Add this to your existing Location model
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_location')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function salesVisits(): HasMany
    {
        return $this->hasMany(SalesVisit::class);
    }

    public function preferredDeliveryContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'preferred_delivery_contact_id');
    }

    public function getFullAddressAttribute(): string
    {
        if (!$this->address_line1) {
            return '';
        }

        return collect([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
        ])->filter()->join(', ');
    }
}
