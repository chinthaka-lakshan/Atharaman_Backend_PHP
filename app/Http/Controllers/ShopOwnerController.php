<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ShopOwner;
use Illuminate\Support\Facades\Auth;

class ShopOwnerController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'shopOwnerName' => 'required|string|max:255',
            'shopOwnerNic' => 'required|string|max:255',
            'businessMail' => 'required|email',
            'contactNumber' => 'required|string|max:15',
            'user_id' => 'required|exists:users,id'
        ]);

        $shopOwner = ShopOwner::create([
            'shopOwnerName' => $request->shopOwnerName,
            'shopOwnerNic' => $request->shopOwnerNic,
            'businessMail' => $request->businessMail,
            'contactNumber' => $request->contactNumber,
            'user_id' => $request->user_id,
        ]);

        return response()->json([
            'message' => 'Shop Owner created successfully!',
            'shopOwner' => $shopOwner
        ]);
    }

    public function update(Request $request, $id)
    {
        $shopOwner = ShopOwner::findOrFail($id);

        $validated = $request->validate([
            'shopOwnerName' => 'sometimes|required|string|max:255',
            'shopOwnerNic' => 'sometimes|required|string|max:255',
            'businessMail' => 'sometimes|required|email',
            'contactNumber' => 'sometimes|required|string|max:15',
            'user_id' => 'sometimes|required|exists:users,id'
        ]);

        // Update other fields
        $shopOwner->fill($request->only([
            'shopOwnerName', 'shopOwnerNic', 'businessMail', 'contactNumber'
        ]));

        $shopOwner->save();

        return response()->json([
            'message' => 'Shop Owner updated successfully!',
            'shopOwner' => $shopOwner->fresh()
        ]);
    }

    public function show($id)
    {
        $shopOwner = ShopOwner::findOrFail($id);
        return response()->json($shopOwner);
    }

    public function index()
    {
        $shopOwners = ShopOwner::all();
        return response()->json($shopOwners);
    }

    public function destroy($id)
    {
        $shopOwner = ShopOwner::findOrFail($id);
        $shopOwner->delete();

        return response()->json(['message' => 'Shop Owner deleted successfully!']);
    }
}
