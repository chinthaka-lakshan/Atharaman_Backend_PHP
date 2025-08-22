<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
        public function run(): void {
        Role::insert([
            ['name' => 'user'],
            ['name' => 'guide'],
            ['name' => 'shop_owner'],
            ['name' => 'hotel_owner'],
            ['name' => 'vehicle_owner']
        ]);
    }
}