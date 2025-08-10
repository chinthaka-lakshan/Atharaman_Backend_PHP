<?php

namespace App\Http\Controllers;

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
        // Logic to validate and create a new item
        $validatedData = $request->validate([
            'itemName' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'locations' => 'nullable|array',
            'itemImage' => 'nullable|array',
            'shop_id' => 'required|exists:shops,id',
        ]);
        $images = [];
        if ($request->hasFile('itemImage')) {
            foreach ($request->file('itemImage') as $image) {
                $path = $image->store('items', 'public'); // saves in storage/app/public/items
                $images[] = $path;
            }
            $validatedData['itemImage'] = json_encode($images);
        } else {
            $validatedData['itemImage'] = json_encode([]);
        }

        $item = Item::create([
            'itemName' => $validatedData['itemName'],
            'description' => $validatedData['description'],
            'price' => $validatedData['price'],
            'locations' => json_encode($validatedData['locations']),
            'itemImage' => $validatedData['itemImage'],
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
}
