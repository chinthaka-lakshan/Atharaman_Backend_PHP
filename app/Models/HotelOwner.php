<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelOwner extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_owner_name',
        'hotel_owner_nic',
        'hotel_owner_dob',
        'hotel_owner_address',
        'business_mail',
        'contact_number',
        'whatsapp_number',
        'user_id'
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