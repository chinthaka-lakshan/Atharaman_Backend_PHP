<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopOwner extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_owner_name',
        'shop_owner_nic',
        'shop_owner_dob',
        'shop_owner_address',
        'business_mail',
        'contact_number',
        'whatsapp_number',
        'user_id'
    ];

    public function shop()
    {
        return $this->hasMany(Shop::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}