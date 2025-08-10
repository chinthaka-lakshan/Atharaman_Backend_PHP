<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationHotelReviews extends Model
{
    use HasFactory;
    protected $fillable = [
        'rating',
        'comment',
        'type', 
        'reviewImages',
        'user_id',
    ];
    protected $casts = [
        'reviewImages' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
