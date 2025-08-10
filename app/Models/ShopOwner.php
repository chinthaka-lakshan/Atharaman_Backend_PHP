<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopOwner extends Model
{
    use HasFactory;

    protected $fillable = [
        'shopOwnerName',
        'shopOwnerNic',
        'businessMail',
        'contactNumber',
        'user_id',
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
