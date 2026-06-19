<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    // =========================
    // GET ALL PRODUCTS
    // =========================
    public function index(Request $request)
    {
        $query = Product::with('category', 'images');

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Sort
        if ($request->has('sort')) {
            switch ($request->sort) {
                case 'price_asc':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('price', 'desc');
                    break;
                case 'name_asc':
                    $query->orderBy('name', 'asc');
                    break;
                case 'name_desc':
                    $query->orderBy('name', 'desc');
                    break;
                default:
                    $query->latest();
            }
        } else {
            $query->latest();
        }

        // Pagination
        $perPage = $request->per_page ?? 12;
        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    // =========================
    // GET FEATURED PRODUCTS
    // =========================
    public function featured()
    {
        $products = Product::with('category', 'images')
            ->where('is_active', true)
            ->where('featured', true)
            ->limit(8)
            ->get();

        return response()->json($products);
    }

    // =========================
    // GET SINGLE PRODUCT
    // =========================
    public function show($id)
    {
        $product = Product::with('category', 'images')->findOrFail($id);
        return response()->json($product);
    }

    // =========================
    // CREATE PRODUCT (ADMIN)
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'brand' => 'nullable|string',
            'sku' => 'nullable|string|unique:products',
            'thumbnail' => 'nullable|string',
        ]);

        $product = Product::create([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock ?? 0,
            'brand' => $request->brand,
            'sku' => $request->sku,
            'thumbnail' => $request->thumbnail,
            'video_url' => $request->video_url,
            'is_active' => true,
            'featured' => $request->featured ?? false,
        ]);

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product
        ], 201);
    }

    // =========================
    // UPDATE PRODUCT (ADMIN)
    // =========================
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $product->update([
            'category_id' => $request->category_id ?? $product->category_id,
            'name' => $request->name ?? $product->name,
            'slug' => $request->name ? Str::slug($request->name) : $product->slug,
            'description' => $request->description ?? $product->description,
            'price' => $request->price ?? $product->price,
            'stock' => $request->stock ?? $product->stock,
            'brand' => $request->brand ?? $product->brand,
            'sku' => $request->sku ?? $product->sku,
            'thumbnail' => $request->thumbnail ?? $product->thumbnail,
            'video_url' => $request->video_url ?? $product->video_url,
            'is_active' => $request->is_active ?? $product->is_active,
            'featured' => $request->featured ?? $product->featured,
        ]);

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product
        ]);
    }

    // =========================
    // DELETE PRODUCT (ADMIN)
    // =========================
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully'
        ]);
    }
}
