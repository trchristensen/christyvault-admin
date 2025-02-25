<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'contact_name',
    ];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'customer_contact')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function salesVisits(): HasMany
    {
        return $this->hasMany(SalesVisit::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function locations(): MorphToMany
    {
        return $this->morphToMany(Location::class, 'locationable')
            ->withPivot('type', 'sequence')
            ->withTimestamps();
    }

    public function primaryLocation()
    {
        return $this->locations()
            ->wherePivot('type', 'primary')
            ->first();
    }

    protected static function booted()
    {
        static::created(function ($customer) {
            \Log::info('Customer created', [
                'customer' => $customer->toArray(),
                'has_location_data' => request()->has('location'),
                'location_data' => request()->input('location'),
            ]);
            if (request()->has('location')) {
                $locationData = request()->input('location');
                $locationData['name'] = $customer->name;
                $locationData['location_type'] = 'cemetery';

                // Create the location
                $location = Location::create($locationData);

                // Attach the location with pivot data
                $customer->locations()->attach($location->id, [
                    'type' => 'primary',
                    'sequence' => 1
                ]);

                // Force reload the relationship
                $customer->load('locations');
            }
        });

        static::updated(function ($customer) {
            \Log::info('Customer updated', [
                'customer' => $customer->toArray(),
                'has_location_data' => request()->has('location'),
                'location_data' => request()->input('location'),
            ]);
            if (request()->has('location')) {
                $locationData = request()->input('location');
                $locationData['name'] = $customer->name;
                $locationData['location_type'] = 'cemetery';

                $location = $customer->locations()->first();

                if ($location) {
                    $location->update($locationData);
                } else {
                    // Create and attach if no location exists
                    $location = Location::create($locationData);
                    $customer->locations()->attach($location->id, [
                        'type' => 'primary',
                        'sequence' => 1
                    ]);
                }

                // Force reload the relationship
                $customer->load('locations');
            }
        });
    }

    public function getLocationAttribute()
    {
        return $this->locations()->first();
    }

    // Add this to make sure we're always loading the location relationship
    protected $with = ['locations'];
}
