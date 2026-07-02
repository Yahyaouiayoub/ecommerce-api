<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomepageFeature;
use Illuminate\Http\JsonResponse;

class HomepageFeatureController extends Controller
{
    /**
     * Get all active feature cards, sorted by display order.
     */
    public function index(): JsonResponse
    {
        $features = HomepageFeature::active()
            ->sorted()
            ->get()
            ->map(fn($f) => [
                'id'          => $f->id,
                'icon_key'    => $f->icon_key,
                'title'       => $f->title,
                'description' => $f->description,
                'link_url'    => $f->link_url,
                'sort_order'  => $f->sort_order,
            ]);

        return response()->json([
            'data' => $features,
        ]);
    }
}
