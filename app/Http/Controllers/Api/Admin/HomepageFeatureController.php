<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HomepageFeatureController extends Controller
{
    /**
     * Get all homepage features (paginated).
     */
    public function index(): JsonResponse
    {
        $features = HomepageFeature::sorted()->paginate(20);

        return response()->json($features);
    }

    /**
     * Get a single feature by ID.
     */
    public function show(int $id): JsonResponse
    {
        $feature = HomepageFeature::findOrFail($id);

        return response()->json([
            'data' => $feature,
        ]);
    }

    /**
     * Create a new feature card.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'icon_key'    => ['required', 'string', 'max:100'],
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'link_url'    => 'nullable|string|max:500',
            'sort_order'  => 'integer|min:0|max:999',
            'is_active'   => 'boolean',
        ]);

        $feature = HomepageFeature::create($validated);

        return response()->json([
            'message' => 'Feature card created successfully.',
            'data'    => $feature,
        ], 201);
    }

    /**
     * Update an existing feature card.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $feature = HomepageFeature::findOrFail($id);

        $validated = $request->validate([
            'icon_key'    => ['required', 'string', 'max:100'],
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'link_url'    => 'nullable|string|max:500',
            'sort_order'  => 'integer|min:0|max:999',
            'is_active'   => 'boolean',
        ]);

        $feature->update($validated);

        return response()->json([
            'message' => 'Feature card updated successfully.',
            'data'    => $feature->fresh(),
        ]);
    }

    /**
     * Delete a feature card.
     */
    public function destroy(int $id): JsonResponse
    {
        $feature = HomepageFeature::findOrFail($id);
        $feature->delete();

        return response()->json([
            'message' => 'Feature card deleted successfully.',
        ]);
    }

    /**
     * Toggle the active status of a feature card.
     */
    public function toggleActive(int $id): JsonResponse
    {
        $feature = HomepageFeature::findOrFail($id);
        $feature->update(['is_active' => !$feature->is_active]);

        return response()->json([
            'message' => $feature->is_active ? 'Feature card enabled.' : 'Feature card disabled.',
            'data'    => $feature->fresh(),
        ]);
    }

    /**
     * Reorder features by accepting an array of { id, sort_order } pairs.
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items'   => 'required|array|min:1',
            'items.*' => 'required|array:sort_order,id',
            'items.*.id'         => 'required|integer|exists:homepage_features,id',
            'items.*.sort_order' => 'required|integer|min:0|max:999',
        ]);

        foreach ($request->items as $item) {
            HomepageFeature::where('id', $item['id'])->update([
                'sort_order' => $item['sort_order'],
            ]);
        }

        return response()->json([
            'message' => 'Features reordered successfully.',
        ]);
    }
}
