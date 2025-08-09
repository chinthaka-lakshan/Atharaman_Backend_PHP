<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelOwner extends Model
{
    use HasFactory;
    protected $fillable = [
        'hotelOwnerName',
        'hotelOwnerNic',
        'businessMail',
        'contactNumber',
        'user_id',
    ];

    public function hotel()
    {
        return $this->hasMany(Hotel::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
