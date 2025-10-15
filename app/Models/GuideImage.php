<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuideImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'guide_id',
        'image_path',
        'order_index',
        'alt_text'
    ];

    protected $appends = ['image_url'];

    public function guide()
    {
        return $this->belongsTo(Guide::class, 'guide_id');
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