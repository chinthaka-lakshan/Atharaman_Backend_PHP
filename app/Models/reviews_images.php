<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class reviews_images extends Model
{
    use HasFactory;
    protected $fillable = [
        'review_id',
        'image_path',
        'order_index',
        'alt_text'
    ];
    protected $appends = ['image_url'];

    public function review()
    {
        return $this->belongsTo(reviews::class);
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

        static::deleting(function ($model) {
            // Delete the image file from storage
            \Storage::delete($model->image_path);
        });
    }
}
