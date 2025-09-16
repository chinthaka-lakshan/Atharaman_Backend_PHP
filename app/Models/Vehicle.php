<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;
    protected $fillable = [
        'vehicleName',
        'vehicleType',
        'vehicleNumber',
        'pricePerDay',
        'mileagePerDay',
        'fuelType',
        'withDriver',
        'vehicleImage',
        'locations',
        'description',
        'user_id',
        'vehicle_owner_id',
    ];
    protected $casts = [
        'vehicleImage' => 'array',
        'locations' => 'array',
    ];

    public function vehicleOwner()
    {
        return $this->belongsTo(VehicleOwner::class, 'vehicle_owner_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
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
}