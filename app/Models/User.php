<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function guide()
    {
        return $this->hasOne(Guide::class);
    }
    public function shopOwner()
    {
        return $this->hasOne(ShopOwner::class);
    }
    public function hotelOwner()
    {
        return $this->hasOne(HotelOwner::class);
    }
    public function vehicleOwner()
    {
        return $this->hasOne(VehicleOwner::class);
    }
    public function hotels()
    {
        return $this->hasMany(Hotel::class);
    }
    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }
    public function shops()
    {
        return $this->hasMany(Shop::class);
    }
    public function otherReviews()
    {
        return $this->hasMany(OtherReviews::class);
    }
    public function locationHotelReviews()
    {
        return $this->hasMany(LocationHotelReviews::class);
    }


    public function roles() {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    public function roleRequests() {
        return $this->hasMany(RoleRequest::class);
    }

    public function hasRole($roleName) {
        return $this->roles()->where('name', $roleName)->exists();
    }

    // NEW METHOD: Check if user has any business roles
    public function hasBusinessRole() {
        return $this->roles()->whereIn('name', ['guide', 'shop_owner', 'hotel_owner', 'vehicle_owner'])->exists();
    }

    // NEW METHOD: Get all business roles user has
    public function getBusinessRoles() {
        return $this->roles()->whereIn('name', ['guide', 'shop_owner', 'hotel_owner', 'vehicle_owner'])->get();
    }


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // NEW: Add this method to easily load all relationships for profile
    public function loadBusinessProfile() {
        return $this->load([
            'roles',
            'guide',
            'shopOwner', 
            'hotelOwner',
            'vehicleOwner',
            'roleRequests'
        ]);
    }
}