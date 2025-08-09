<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    use HasFactory;
    protected $fillable = [
        'hotelName',
        'hotelAddress',
        'businessMail',
        'contactNumber',
        'whatsappNumber',
        'hotelImage',
        'locations',
        'description',
        'user_id',
        'hotel_owner_id',
    ];
    protected $casts = [
        'hotelImage' => 'array',
        'locations' => 'array',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function hotelOwner()
    {
        return $this->belongsTo(HotelOwner::class, 'hotel_owner_id');
    }
}
