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
        'guideImage',
        'languages',
        'locations',
        'description',
        'user_id',
    ];
    protected $casts = [
        'guideImage' => 'array',
        'languages' => 'array',
        'locations' => 'array',
    ];

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