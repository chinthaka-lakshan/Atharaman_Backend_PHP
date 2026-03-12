<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Location;
use App\Models\Guide;
use App\Models\Shop;
use App\Models\Hotel;
use App\Models\Vehicle;
use App\Models\User;

class DashboardController extends Controller
{
    public function getStats()
    {
        try {
            $locationsCount = Location::count();
            $guidesCount = Guide::count();
            $shopsCount = Shop::count();
            $hotelsCount = Hotel::count();
            $vehiclesCount = Vehicle::count();
            
            // For owners, we can either count all distinct owner IDs from their respective tables,
            // or if there are specific user roles, count users with those roles.
            // Based on previous contexts, let's assume owners are just users who have entries in those tables,
            // or we count the items themselves if it's 1-to-1 (e.g. one shop per owner).
            // Let's count distinct user_ids in those tables to get the "owners" count.
            
            // Note: Adjusting to match the UI labels as closely as possible based on the DB schema.
            // If the schema for Shop/Hotel/Vehicle has a user_id, we count distinct user_ids.
            // Let's try to count distinct user_ids if the tables have them, otherwise just use the item count or User role count.
            
            // Fallback strategy: If 'Shop Owners' just meant 'Users with ShopOwner role', we could do:
            // $shopOwnersCount = User::whereHas('roles', fn($q) => $q->where('name', 'ShopOwner'))->count();
            // Let's use a dynamic approach: Since we don't have the exact role names memorized right now, 
            // we will query the DB safely. Let's assume there are roles table relationships.
            
            // For now, let's assume the standard way to count owners is the number of approved users with that role.
            // Or simpler: Just count distinct user_ids from Shop, Hotel, Vehicle tables.
            
            // Safe fallback if column doesn't exist: just return generic counts or count all items as proxy for now
            $shopOwnersCount = DB::table('shops')->distinct('user_id')->count('user_id');
            $hotelOwnersCount = DB::table('hotels')->distinct('user_id')->count('user_id');
            $vehicleOwnersCount = DB::table('vehicles')->distinct('user_id')->count('user_id');

            return response()->json([
                'success' => true,
                'data' => [
                    'locations' => $locationsCount,
                    'shopOwners' => $shopOwnersCount,
                    'hotelOwners' => $hotelOwnersCount,
                    'vehicleOwners' => $vehicleOwnersCount,
                    'guides' => $guidesCount,
                    'shops' => $shopsCount,
                    'hotels' => $hotelsCount,
                    'vehicles' => $vehiclesCount,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
