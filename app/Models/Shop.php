<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_name',
        'nearest_city',
        'shop_address',
        'contact_number',
        'whatsapp_number',
        'short_description',
        'long_description',
        'locations',
        'user_id',
        'shop_owner_id',
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
        return $this->hasMany(ShopImage::class, 'shop_id')->orderBy('order_index');
    }

    public function shopOwner()
    {
        return $this->belongsTo(ShopOwner::class,'shop_owner_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class);
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