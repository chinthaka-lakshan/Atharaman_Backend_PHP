<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_name',
        'vehicle_type',
        'reg_number',
        'manufactured_year',
        'no_of_passengers',
        'fuel_type',
        'driver_status',
        'short_description',
        'long_description',
        'price_per_day',
        'mileage_per_day',
        'locations',
        'user_id',
        'vehicle_owner_id',
    ];

    protected $casts = [
        'locations' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function images()
    {
        return $this->hasMany(VehicleImage::class, 'vehicle_id')->orderBy('order_index');
    }

    public function vehicleOwner()
    {
        return $this->belongsTo(VehicleOwner::class, 'vehicle_owner_id');
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'entity');
    }

    public function getAverageRatingAttribute()
    {
        return $this->reviews()->avg('rating') ?: 0;
    }

    public function getReviewCountAttribute()
    {
        return $this->reviews()->count();
    }

    // Helper method to get first image (for thumbnails)
    public function getFeaturedImageAttribute()
    {
        return $this->images->first();
    }

    // Helper method to get all image URLs
    public function getImageUrlsAttribute()
    {
        return $this->images->pluck('image_url');
    }
}