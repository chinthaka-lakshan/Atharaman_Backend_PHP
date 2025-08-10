<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    use HasFactory;
    protected $fillable=[
        'shopName',
        'shopAddress',
        'description',
        'locations',
        'shopImage',
        'user_id',
        'shop_owner_id'
    ];
    protected $casts = [
        'shopImage' => 'array',
        'locations' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shopOwner()
    {
        return $this->belongsTo(ShopOwner::class,'shop_owner_id');
    }
    public function items()
    {
        return $this->hasMany(Item::class);
    }

}
