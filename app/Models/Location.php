<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;
    protected $fillable = [
        'locationName',
        'shortDescription',
        'longDescription',
        'province',
        'latitude',
        'longitude',
        'locationImage',
    ];
    protected $casts = [
        'locationImage' => 'array',
    ];
}
