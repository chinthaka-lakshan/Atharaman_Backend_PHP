<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'entity_type',
        'entity_id',
        'rating',
        'comment',
        'image1',
        'image2',
        'image3',
        'image4',
        'image5',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function entity()
    {
        return $this->morphTo();
    }

    // Return all non-null images as array
    public function getImageList(): array
{
    return array_filter([
        $this->image1,
        $this->image2,
        $this->image3,
        $this->image4,
        $this->image5,
    ]);
}

public function hasImages()
{
    return count($this->getImageList()) > 0;
}

public function imageCount()
{
    return count($this->getImageList());
}

}
