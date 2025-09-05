<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'entity_type',
        'entity_id',
        'rating',
        'comment',
        'images',
    ];

    protected $casts = [
        'rating' => 'integer',
        'images' => 'array',
    ];

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Polymorphic relationship to get the actual entity (location, hotel, etc.)
    public function entity()
    {
        return $this->morphTo();
    }

    // Helper method to check if this review has images
    public function hasImages()
    {
        return !empty($this->images) && is_array($this->images) && count($this->images) > 0;
    }

    // Helper method to get image count
    public function imageCount()
    {
        return $this->hasImages() ? count($this->images) : 0;
    }
}