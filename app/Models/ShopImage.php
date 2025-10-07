<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'image_path',
        'order_index',
        'alt_text'
    ];

    protected $appends = ['image_url'];

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    // Simple accessor
    public function getImageUrlAttribute()
    {
        return asset('storage/' . $this->image_path);
    }

    // Clean up image file when model is deleted
    public static function boot()
    {
        parent::boot();

        static::deleting(function ($image) {
            if (\Storage::disk('public')->exists($image->image_path)) {
                \Storage::disk('public')->delete($image->image_path);
            }
        });
    }
}