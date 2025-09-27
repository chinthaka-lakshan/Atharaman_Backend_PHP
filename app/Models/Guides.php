<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guides extends Model
{
    use HasFactory;

    protected $fillable = [
        'guideName',
        'guideNic',
        'businessMail',
        'personalNumber',
        'whatsappNumber',
        'languages',
        'locations',
        'description',
        'user_id',
    ];
    protected $casts = [
        'languages' => 'array',
        'locations' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function images()
    {
        return $this->hasMany(GuideImage::class, 'guide_id')->orderBy('order_index');
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