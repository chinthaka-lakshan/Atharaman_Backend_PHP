<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Locations extends Model
{
    use HasFactory;
    protected $filleble = [
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
