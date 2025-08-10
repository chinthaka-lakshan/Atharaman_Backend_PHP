<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;
    protected $fillable = [
        'itemName',
        'description',
        'price',
        'locations',
        'itemImage',
        'shop_id',
    ];
    protected $casts = [
        'itemImage' => 'array',
        'locations' => 'array',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
