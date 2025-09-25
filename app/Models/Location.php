<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;
    protected $fillable = [
        'locationName',
        'shortDescription',
        'longDescription',
        'province',
        'latitude',
        'longitude',
        'locationType',
    ];

    public function images()
    {
        return $this->hasMany(LocationImage::class)->orderBy('order_index');
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