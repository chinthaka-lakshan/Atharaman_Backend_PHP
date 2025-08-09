<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guides extends Model
{
    use HasFactory;

    protected $fillable = [
        'guideName',
        'guideNic',
        'businessMail',
        'personalNumber',
        'whatsappNumber',
        'guideImage',
        'languages',
        'locations',
        'description',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
