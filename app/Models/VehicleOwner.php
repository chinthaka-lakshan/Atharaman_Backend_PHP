<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleOwner extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicleOwnerName',
        'vehicleOwnerNic',
        'businessMail',
        'personalNumber',
        'whatsappNumber',
        'locations',
        'description',
        'user_id',
    ];
    protected $casts = [
        'locations' => 'array',
    ];
    public function vehicle()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
