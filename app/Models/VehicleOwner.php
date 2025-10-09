<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleOwner extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_owner_name',
        'vehicle_owner_nic',
        'vehicle_owner_dob',
        'vehicle_owner_address',
        'business_mail',
        'contact_number',
        'whatsapp_number',
        'locations',
        'user_id'
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