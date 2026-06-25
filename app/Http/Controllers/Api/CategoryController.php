<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    // =========================
    // GET ALL CATEGORIES
    // =========================
    public function index()
    {
        $categories = Category::withCount('products')->get();
        return response()->json($categories);
    }

    // =========================
    // GET SINGLE CATEGORY
    // =========================
    public function show($id)
    {
        $category = Category::with('products')->findOrFail($id);
        return response()->json($category);
    }

    // =========================
    // CREATE CATEGORY (ADMIN)
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_fr' => 'nullable|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'name_es' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'name_en' => $request->name_en,
            'name_fr' => $request->name_fr,
            'name_ar' => $request->name_ar,
            'name_es' => $request->name_es,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'image' => $this->uploadImage($request, 'image', 'categories'),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category
        ], 201);
    }

    // =========================
    // UPDATE CATEGORY (ADMIN)
    // =========================
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'nullable|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_fr' => 'nullable|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'name_es' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_image' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $updateData = [
            'name' => $request->name ?? $category->name,
            'name_en' => $request->name_en ?? $category->name_en,
            'name_fr' => $request->name_fr ?? $category->name_fr,
            'name_ar' => $request->name_ar ?? $category->name_ar,
            'name_es' => $request->name_es ?? $category->name_es,
            'slug' => $request->name ? Str::slug($request->name) : $category->slug,
            'description' => $request->description ?? $category->description,
            'is_active' => $request->is_active ?? $category->is_active,
        ];

        // Update image if new file uploaded
        if ($request->hasFile('image')) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $updateData['image'] = $request->file('image')->store('categories', 'public');
        }

        // Remove image if requested
        if ($request->boolean('remove_image') && $category->image) {
            Storage::disk('public')->delete($category->image);
            $updateData['image'] = null;
        }

        $category->update($updateData);

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category
        ]);
    }

    // =========================
    // DELETE CATEGORY (ADMIN)
    // =========================
    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        // Delete category image from storage
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
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
