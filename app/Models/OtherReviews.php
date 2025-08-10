<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtherReviews extends Model
{
    use HasFactory;
    protected $fillable = [
        'review',
        'rating',
        'type',
        'user_id',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
