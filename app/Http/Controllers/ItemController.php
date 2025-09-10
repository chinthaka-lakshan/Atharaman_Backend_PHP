<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Shop;
use App\Models\ShopOwner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index()
    {
        // Logic to retrieve and return all items
        $items = Item::all();
        return response()->json($items);
    }

    public function show($id)
    {
        // Logic to retrieve and return a specific item by ID
        $item = Item::find($id);
        if ($item) {
            return response()->json($item);
        }
        return response()->json(['message' => 'Item not found'], 404);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'itemName' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'locations' => 'nullable|array',
            'itemImage' => 'nullable|array',
            'itemImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'shop_id' => 'required|exists:shops,id',
        ]);

        $images = [];
        if ($request->hasFile('itemImage')) {
            foreach ($request->file('itemImage') as $image) {
                $path = $image->store('items', 'public');
                $images[] = $path;
            }
        }
        $itemImage = json_encode($images);

        $item = Item::create([
            'itemName' => $validatedData['itemName'],
            'description' => $validatedData['description'] ?? null,
            'price' => $validatedData['price'],
            'locations' => isset($validatedData['locations']) ? json_encode($validatedData['locations']) : json_encode([]),
            'itemImage' => $itemImage,
            'shop_id' => $validatedData['shop_id'],
        ]);

        return response()->json([
            'message' => 'Item created successfully!',
            'item' => $item
        ]);
    }

    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'itemName' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric',
            'locations' => 'nullable|array',
            'itemImage' => 'nullable|array',
            'itemImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'shop_id' => 'sometimes|required|exists:shops,id',
        ]);

        $item = Item::findOrFail($id);

        // Handle images
        $existingImages = json_decode($item->itemImage, true) ?? [];
        $images = [];
        if ($request->hasFile('itemImage')) {
            foreach ($request->file('itemImage') as $image) {
                $path = $image->store('items', 'public');
                $images[] = $path;
            }
            $item->itemImage = json_encode($images);
        } else {
            $item->itemImage = json_encode($existingImages);
        }

        if ($request->filled('itemName')) $item->itemName = $request->itemName;
        if ($request->filled('description')) $item->description = $request->description;
        if ($request->filled('price')) $item->price = $request->price;
        if ($request->has('locations')) $item->locations = is_array($request->locations) ? json_encode($request->locations) : $item->locations;
        if ($request->filled('shop_id')) $item->shop_id = $request->shop_id;

        $item->save();

        return response()->json([
            'message' => 'Item updated successfully!',
            'item' => $item
        ]);
    }

    public function getByShop($shopId)
    {
        $items = Item::where('shop_id', $shopId)->get();
        return response()->json($items);
    }   

    public function destroy($id)
    {
        $item = Item::find($id);
        if (!$item) {
            return response()->json(['message' => 'Item not found'], 404);
        }
        $item->delete();
        return response()->json(['message' => 'Item deleted successfully!']);
    }

    public function getByLocation($location)
    {
        // Assumes locations is a JSON array column
        $items = Item::whereJsonContains('locations', $location)->get();
        return response()->json($items);
    }

    // User side methods
    public function getByAuthenticatedUser(Request $request)
    {
        $shopOwner = ShopOwner::where('user_id', $request->user()->id)->first();
        
        if (!$shopOwner) {
            return response()->json(['error' => 'Shop owner not found'], 404);
        }

        $items = Item::whereHas('shop', function($query) use ($shopOwner) {
            $query->where('shop_owner_id', $shopOwner->id);
        })->get();

        return response()->json($items);
    }

    public function getByAuthenticatedShop(Request $request, $shopId)
    {
        $shopOwner = ShopOwner::where('user_id', $request->user()->id)->first();
        
        if (!$shopOwner) {
            return response()->json(['error' => 'Shop owner not found'], 404);
        }

        // Verify that the shop belongs to the authenticated shop owner
        $shop = Shop::where('id', $shopId)
                    ->where('shop_owner_id', $shopOwner->id)
                    ->firstOrFail();

        $items = Item::where('shop_id', $shopId)->get();
        return response()->json($items);
    }

    public function storeByAuthenticatedUser(Request $request)
    {
        $shopOwner = ShopOwner::where('user_id', $request->user()->id)->first();
        
        if (!$shopOwner) {
            return response()->json(['error' => 'Shop owner not found'], 404);
        }

        $validatedData = $request->validate([
            'itemName' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'locations' => 'nullable|array',
            'itemImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'shop_id' => 'required|exists:shops,id'
        ]);

        // Verify that the shop belongs to the authenticated shop owner
        $shop = Shop::where('id', $validatedData['shop_id'])
                    ->where('shop_owner_id', $shopOwner->id)
                    ->firstOrFail();

        $images = [];
        if ($request->hasFile('itemImage')) {
            foreach ($request->file('itemImage') as $image) {
                $path = $image->store('items', 'public');
                $images[] = $path;
            }
        }
        $itemImage = json_encode($images);

        $item = Item::create([
            'itemName' => $validatedData['itemName'],
            'description' => $validatedData['description'] ?? null,
            'price' => $validatedData['price'],
            'locations' => isset($validatedData['locations']) ? json_encode($validatedData['locations']) : json_encode([]),
            'itemImage' => $itemImage,
            'shop_id' => $validatedData['shop_id'],
        ]);

        return response()->json([
            'message' => 'Item created successfully!',
            'item' => $item
        ]);
    }

    public function updateByAuthenticatedUser(Request $request, $id)
    {
        $shopOwner = ShopOwner::where('user_id', $request->user()->id)->first();
        
        if (!$shopOwner) {
            return response()->json(['error' => 'Shop owner not found'], 404);
        }

        $item = Item::where('id', $id)
                    ->whereHas('shop', function($query) use ($shopOwner) {
                        $query->where('shop_owner_id', $shopOwner->id);
                    })
                    ->firstOrFail();

        $validatedData = $request->validate([
            'itemName' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric',
            'locations' => 'nullable|array',
            'itemImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'shop_id' => 'sometimes|required|exists:shops,id'
        ]);

        // If shop_id is being updated, verify the new shop belongs to the owner
        if ($request->has('shop_id') && $request->shop_id != $item->shop_id) {
            $shop = Shop::where('id', $request->shop_id)
                        ->where('shop_owner_id', $shopOwner->id)
                        ->firstOrFail();
        }

        // Handle images
        $existingImages = json_decode($item->itemImage, true) ?? [];
        $images = [];
        if ($request->hasFile('itemImage')) {
            foreach ($request->file('itemImage') as $image) {
                $path = $image->store('items', 'public');
                $images[] = $path;
            }
            $item->itemImage = json_encode($images);
        } else {
            $item->itemImage = json_encode($existingImages);
        }

        if ($request->filled('itemName')) $item->itemName = $request->itemName;
        if ($request->filled('description')) $item->description = $request->description;
        if ($request->filled('price')) $item->price = $request->price;
        if ($request->has('locations')) $item->locations = is_array($request->locations) ? json_encode($request->locations) : $item->locations;
        if ($request->filled('shop_id')) $item->shop_id = $request->shop_id;

        $item->save();

        return response()->json([
            'message' => 'Item updated successfully!',
            'item' => $item
        ]);
    }

    public function deleteByAuthenticatedUser(Request $request, $id)
    {
        $shopOwner = ShopOwner::where('user_id', $request->user()->id)->first();
        
        if (!$shopOwner) {
            return response()->json(['error' => 'Shop owner not found'], 404);
        }

        $item = Item::where('id', $id)
                    ->whereHas('shop', function($query) use ($shopOwner) {
                        $query->where('shop_owner_id', $shopOwner->id);
                    })
                    ->firstOrFail();

        $item->delete();

        return response()->json(['message' => 'Item deleted successfully!']);
    }
}
