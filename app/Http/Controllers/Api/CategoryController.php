<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
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
            'description' => 'nullable|string',
            'image' => 'nullable|string',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'image' => $request->image,
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

        $category->update([
            'name' => $request->name ?? $category->name,
            'slug' => $request->name ? Str::slug($request->name) : $category->slug,
            'description' => $request->description ?? $category->description,
            'image' => $request->image ?? $category->image,
            'is_active' => $request->is_active ?? $category->is_active,
        ]);

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
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }
}
