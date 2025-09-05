<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebsiteReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope for getting reviews with specific rating or higher
    public function scopeWithRating($query, $minRating = 1)
    {
        return $query->where('rating', '>=', $minRating);
    }

    // Scope for recent reviews
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}