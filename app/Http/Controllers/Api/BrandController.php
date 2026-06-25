<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    // =========================
    // GET ALL BRANDS
    // =========================
    public function index()
    {
        $brands = Brand::withCount('products')
            ->where('is_active', true)
            ->get();
        return response()->json($brands);
    }

    // =========================
    // GET SINGLE BRAND
    // =========================
    public function show($id)
    {
        $brand = Brand::with('products')->findOrFail($id);
        return response()->json($brand);
    }

    // =========================
    // ADMIN: GET ALL BRANDS (includes inactive)
    // =========================
    public function adminIndex()
    {
        $brands = Brand::withCount('products')->get();
        return response()->json($brands);
    }

    // =========================
    // CREATE BRAND (ADMIN)
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $brand = Brand::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'image' => $this->uploadImage($request, 'image', 'brands'),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Brand created successfully',
            'brand' => $brand
        ], 201);
    }

    // =========================
    // UPDATE BRAND (ADMIN)
    // =========================
    public function update(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);

        $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_image' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $updateData = [
            'name' => $request->name ?? $brand->name,
            'slug' => $request->name ? Str::slug($request->name) : $brand->slug,
            'description' => $request->description ?? $brand->description,
            'is_active' => $request->is_active ?? $brand->is_active,
        ];

        // Update image if new file uploaded
        if ($request->hasFile('image')) {
            if ($brand->image) {
                Storage::disk('public')->delete($brand->image);
            }
            $updateData['image'] = $request->file('image')->store('brands', 'public');
        }

        // Remove image if requested
        if ($request->boolean('remove_image') && $brand->image) {
            Storage::disk('public')->delete($brand->image);
            $updateData['image'] = null;
        }

        $brand->update($updateData);

        return response()->json([
            'message' => 'Brand updated successfully',
            'brand' => $brand
        ]);
    }

    // =========================
    // DELETE BRAND (ADMIN)
    // =========================
    public function destroy($id)
    {
        $brand = Brand::findOrFail($id);

        // Delete brand image from storage
        if ($brand->image) {
            Storage::disk('public')->delete($brand->image);
        }

        $brand->delete();

        return response()->json([
            'message' => 'Brand deleted successfully'
        ]);
    }

    // =========================
    // UPLOAD IMAGE HELPER
    // =========================
    private function uploadImage(Request $request, string $fieldName, string $path): ?string
    {
        if ($request->hasFile($fieldName)) {
            return $request->file($fieldName)->store($path, 'public');
        }
        return $request->input($fieldName);
    }
}
