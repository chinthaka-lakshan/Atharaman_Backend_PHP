<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleOwner extends Model
{
    use HasFactory;

    protected $filable = [
        'vehicleOwnerName',
        'vehicleOwnerNic',
        'businessMail',
        'personalNumber',
        'whatsappNumber',
        'locations',
        'description',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
