<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_name',
        'nearest_city',
        'hotel_address',
        'business_mail',
        'contact_number',
        'whatsapp_number',
        'short_description',
        'long_description',
        'locations',
        'user_id',
        'hotel_owner_id',
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
        return $this->hasMany(HotelImage::class, 'hotel_id')->orderBy('order_index');
    }

    public function hotelOwner()
    {
        return $this->belongsTo(HotelOwner::class, 'hotel_owner_id');
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