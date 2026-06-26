<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    // =========================
    // LIST VARIANTS FOR A PRODUCT
    // =========================
    public function index($productId)
    {
        $product = Product::findOrFail($productId);
        $variants = $product->variants()->orderBy('sort_order')->get();

        return response()->json([
            'data' => $variants,
            'attribute_groups' => ProductVariant::getAttributeGroups((int) $productId),
        ]);
    }

    // =========================
    // GET SINGLE VARIANT
    // =========================
    public function show($id)
    {
        $variant = ProductVariant::with('product')->findOrFail($id);

        return response()->json([
            'data' => $variant,
        ]);
    }

    // =========================
    // ADMIN: CREATE VARIANT
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'name' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'sku' => 'nullable|string|unique:product_variants,sku',
            'color' => 'nullable|string|max:100',
            'size' => 'nullable|string|max:100',
            'storage' => 'nullable|string|max:100',
            'attributes' => 'nullable|array',
            'attributes.*' => 'string|max:255',
            'is_default' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // If setting as default, unset existing defaults
        if ($request->boolean('is_default')) {
            ProductVariant::where('product_id', $request->product_id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $variant = ProductVariant::create([
            'product_id' => $request->product_id,
            'name' => $request->name,
            'price' => $request->price,
            'stock' => $request->stock ?? 0,
            'sku' => $request->sku,
            'color' => $request->color,
            'size' => $request->size,
            'storage' => $request->storage,
            'attributes' => $request->attributes,
            'is_default' => $request->boolean('is_default'),
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return response()->json([
            'message' => 'Variant created successfully',
            'data' => $variant,
        ], 201);
    }

    // =========================
    // ADMIN: UPDATE VARIANT
    // =========================
    public function update(Request $request, $id)
    {
        $variant = ProductVariant::findOrFail($id);

        $request->validate([
            'name' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'sku' => 'nullable|string|unique:product_variants,sku,' . $id,
            'color' => 'nullable|string|max:100',
            'size' => 'nullable|string|max:100',
            'storage' => 'nullable|string|max:100',
            'attributes' => 'nullable|array',
            'attributes.*' => 'string|max:255',
            'is_default' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // If setting as default, unset existing defaults
        if ($request->boolean('is_default')) {
            ProductVariant::where('product_id', $variant->product_id)
                ->where('is_default', true)
                ->where('id', '!=', $variant->id)
                ->update(['is_default' => false]);
        }

        $variant->update($request->only([
            'name', 'price', 'stock', 'sku', 'color', 'size',
            'storage', 'attributes', 'is_default', 'sort_order',
        ]));

        return response()->json([
            'message' => 'Variant updated successfully',
            'data' => $variant->fresh(),
        ]);
    }

    // =========================
    // ADMIN: DELETE VARIANT
    // =========================
    public function destroy($id)
    {
        $variant = ProductVariant::findOrFail($id);
        $variant->delete();

        return response()->json([
            'message' => 'Variant deleted successfully',
        ]);
    }
}
