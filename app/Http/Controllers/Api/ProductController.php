<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    // =========================
    // GET ALL PRODUCTS (cached per filter/sort/page)
    // =========================
    public function index(Request $request, ?CacheService $cacheService = null)
    {
        $cacheService ??= app(CacheService::class);

        $products = $cacheService->rememberProductList($request, function () use ($request) {
            $query = Product::with('category', 'brand', 'images', 'variants')
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->where('stock', '>', 0);

            // Filter by category
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Filter by brand
            if ($request->has('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            // Filter by new arrivals
            if ($request->boolean('new_arrivals')) {
                $query->where('created_at', '>=', now()->subDays(30));
            }

            // Filter by best sellers
            if ($request->boolean('best_sellers')) {
                $query->whereHas('orderItems');
            }

            // Filter by search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%")
                      ->orWhere('sku', 'LIKE', "%{$search}%")
                      ->orWhereHas('category', function ($cq) use ($search) {
                          $cq->where('name', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('brand', function ($bq) use ($search) {
                          $bq->where('name', 'LIKE', "%{$search}%");
                      });
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
                    case 'popular':
                        $query->withCount('reviews')->orderBy('reviews_count', 'desc');
                        break;
                    default:
                        $query->latest();
                }
            } else {
                $query->latest();
            }

            return $query->paginate($request->per_page ?? 12);
        });

        return response()->json($products);
    }

    // =========================
    // GET PRODUCT PRICE RANGE (cached)
    // =========================
    public function priceRange(?CacheService $cacheService = null)
    {
        $cacheService ??= app(CacheService::class);

        $data = $cacheService->rememberPriceRange(function () {
            $min = Product::where('stock', '>', 0)->min('price');
            $max = Product::where('stock', '>', 0)->max('price');

            return ['min_price' => (float) $min, 'max_price' => (float) $max];
        });

        return response()->json($data);
    }

    // =========================
    // GET BEST SELLERS (cached, top products by order count)
    // =========================
    public function bestSellers(?CacheService $cacheService = null)
    {
        $cacheService ??= app(CacheService::class);

        $products = $cacheService->rememberProductCollection('best-sellers', function () {
            return Product::with('category', 'brand', 'images', 'variants')
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->whereIn('id', function ($query) {
                    $query->select('product_id')
                        ->from('order_items')
                        ->groupBy('product_id')
                        ->havingRaw('COALESCE(SUM(quantity), 0) > 0');
                })
                ->withSum('orderItems as total_sold', 'quantity')
                ->orderBy('total_sold', 'desc')
                ->limit(8)
                ->get();
        });

        return response()->json($products);
    }

    // =========================
    // GET FEATURED PRODUCTS (cached)
    // =========================
    public function featured(?CacheService $cacheService = null)
    {
        $cacheService ??= app(CacheService::class);

        $products = $cacheService->rememberProductCollection('featured', function () {
            return Product::with('category', 'brand', 'images', 'variants')
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->where('is_active', true)
                ->where('featured', true)
                ->where('stock', '>', 0)
                ->limit(8)
                ->get();
        });

        return response()->json($products);
    }

    // =========================
    // GET SINGLE PRODUCT (by ID or slug)
    // =========================
    public function show($identifier)
    {
        $query = Product::with('category', 'brand', 'images', 'variants', 'reviews.user:id,first_name,last_name,avatar')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if (is_numeric($identifier)) {
            $product = $query->findOrFail((int) $identifier);
        } else {
            $product = $query->where('slug', $identifier)->firstOrFail();
        }

        // Load related products (same category, exclude current, limit 4)
        $relatedProducts = Product::with('category', 'brand', 'images')
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->inRandomOrder()
            ->limit(4)
            ->get();

        $data = $product->toArray();
        $data['related_products'] = $relatedProducts;

        return response()->json($data);
    }

    // =========================
    // CREATE PRODUCT (ADMIN) — invalidates product caches
    // =========================
    public function store(Request $request)
    {
        app(CacheService::class)->invalidateProducts();
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'margin_percentage' => 'nullable|numeric|min:0|max:1000',
            'discount_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'sku' => 'nullable|string|unique:products',
            'thumbnail' => 'nullable',
            'video_url' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $purchasePrice = (float) ($request->purchase_price ?? 0);
        $marginPercentage = (float) ($request->margin_percentage ?? 0);
        $finalPrice = Product::calculateFinalPrice($purchasePrice, $marginPercentage);

        // Discount validation: block if discount_price < purchase_price
        if ($request->has('discount_price') && $request->discount_price !== null && $request->discount_price !== '') {
            $discountPrice = (float) $request->discount_price;
            if ($marginPercentage <= 0) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'discount_price' => ['Cannot set a discount when margin is zero or negative. Ensure margin_percentage > 0 first.'],
                    ],
                ], 422);
            }
            if ($discountPrice < $purchasePrice) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'discount_price' => ['Discount would cause loss! Discount price cannot be less than purchase price.'],
                    ],
                ], 422);
            }
        }

        $product = Product::create([
            'category_id' => $request->category_id,
            'brand_id' => $request->brand_id,
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'price' => $request->price,
            'purchase_price' => $purchasePrice,
            'margin_percentage' => $marginPercentage,
            'final_price' => $finalPrice,
            'discount_price' => $request->discount_price !== '' ? ($request->discount_price ?? null) : null,
            'stock' => $request->stock ?? 0,
            'sku' => $request->sku,
            'thumbnail' => $this->uploadImage($request, 'thumbnail', 'products/thumbnails'),
            'video_url' => $request->video_url,
            'is_active' => true,
            'featured' => $request->featured ?? false,
        ]);

        // Auto-create expense for stock purchase
        if ($purchasePrice > 0 && ($request->stock ?? 0) > 0) {
            Expense::create([
                'product_id'  => $product->id,
                'title'       => "Product purchase: {$product->name}",
                'amount'      => $purchasePrice * ($request->stock ?? 0),
                'total_cost'  => $purchasePrice * ($request->stock ?? 0),
                'quantity'    => $request->stock ?? 0,
                'category'    => 'products',
                'note'        => "Auto-created from product creation (stock: {$request->stock}, unit cost: {$purchasePrice} MAD)",
                'expense_date' => now()->toDateString(),
                'created_by'  => $request->user()->id,
            ]);
        }

        // Handle multiple image uploads (max 5 total)
        if ($request->hasFile('images')) {
            $sortOrder = 0;
            foreach ($request->file('images') as $image) {
                if ($sortOrder >= 5) break;
                $imagePath = $image->store('products/images', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_url' => $imagePath,
                    'sort_order' => $sortOrder,
                ]);
                $sortOrder++;
            }
        }

        $product->load('images');

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product
        ], 201);
    }

    // =========================
    // UPDATE PRODUCT (ADMIN) — invalidates product caches
    // =========================
    public function update(Request $request, $id)
    {
        app(CacheService::class)->invalidateProducts();
        $product = Product::findOrFail($id);

        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'name' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'margin_percentage' => 'nullable|numeric|min:0|max:1000',
            'discount_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'sku' => 'nullable|string|unique:products,sku,' . $id,
            'thumbnail' => 'nullable',
            'remove_thumbnail' => 'nullable|boolean',
            'video_url' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'featured' => 'nullable|boolean',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'delete_image_ids' => 'nullable|array',
            'delete_image_ids.*' => 'integer|exists:product_images,id',
        ]);

        // Capture old stock before update for expense tracking
        $oldStock = $product->stock;

        // Calculate new final_price if purchase_price or margin_percentage changed
        $purchasePrice = $request->has('purchase_price') ? (float) $request->purchase_price : (float) $product->purchase_price;
        $marginPercentage = $request->has('margin_percentage') ? (float) $request->margin_percentage : (float) $product->margin_percentage;
        $finalPrice = Product::calculateFinalPrice($purchasePrice, $marginPercentage);

        // Discount validation
        $discountPrice = $request->has('discount_price') ? $request->discount_price : $product->discount_price;
        if ($discountPrice !== null && $discountPrice !== '' && (float) $discountPrice > 0) {
            $discountPriceVal = (float) $discountPrice;
            if ($marginPercentage <= 0) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'discount_price' => ['Cannot set a discount when margin is zero or negative. Ensure margin_percentage > 0 first.'],
                    ],
                ], 422);
            }
            if ($discountPriceVal < $purchasePrice) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'discount_price' => ['Discount would cause loss! Discount price cannot be less than purchase price.'],
                    ],
                ], 422);
            }
        }

        $product->update([
            'category_id' => $request->category_id ?? $product->category_id,
            'brand_id' => $request->brand_id ?? $product->brand_id,
            'name' => $request->name ?? $product->name,
            'slug' => $request->name ? Str::slug($request->name) : $product->slug,
            'description' => $request->description ?? $product->description,
            'price' => $request->price ?? $product->price,
            'purchase_price' => $request->has('purchase_price') ? $purchasePrice : $product->purchase_price,
            'margin_percentage' => $request->has('margin_percentage') ? $marginPercentage : $product->margin_percentage,
            'final_price' => $finalPrice,
            'discount_price' => $request->has('discount_price') ? ($request->discount_price !== '' ? (float) $request->discount_price : null) : $product->discount_price,
            'stock' => $request->stock ?? $product->stock,
            'sku' => $request->sku ?? $product->sku,
            'video_url' => $request->video_url ?? $product->video_url,
            'is_active' => $request->is_active ?? $product->is_active,
            'featured' => $request->featured ?? $product->featured,
        ]);

        // Update thumbnail if new file uploaded
        if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail
            if ($product->thumbnail) {
                Storage::disk('public')->delete($product->thumbnail);
            }
            $product->update(['thumbnail' => $this->uploadImage($request, 'thumbnail', 'products/thumbnails')]);
        }

        // Remove thumbnail if requested
        if ($request->boolean('remove_thumbnail') && $product->thumbnail) {
            Storage::disk('public')->delete($product->thumbnail);
            $product->update(['thumbnail' => null]);
        }

        // Delete specified images
        if ($request->has('delete_image_ids')) {
            $imagesToDelete = ProductImage::whereIn('id', $request->delete_image_ids)
                ->where('product_id', $product->id)
                ->get();
            foreach ($imagesToDelete as $img) {
                Storage::disk('public')->delete($img->image_url);
                $img->delete();
            }
        }

        // Count current images after deletion
        $currentCount = $product->images()->count();
        $maxNewImages = 5 - $currentCount;

        // Handle new image uploads
        if ($request->hasFile('images') && $maxNewImages > 0) {
            $sortOrder = $currentCount;
            $uploaded = 0;
            foreach ($request->file('images') as $image) {
                if ($uploaded >= $maxNewImages) break;
                $imagePath = $image->store('products/images', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_url' => $imagePath,
                    'sort_order' => $sortOrder,
                ]);
                $sortOrder++;
                $uploaded++;
            }
        }

        // Auto-create expense if stock was increased
        $newStock = $request->stock ?? $product->stock;
        $stockIncrease = $newStock - $oldStock;
        if ($purchasePrice > 0 && $stockIncrease > 0) {
            Expense::create([
                'product_id'  => $product->id,
                'title'       => "Stock purchase: {$product->name}",
                'amount'      => $purchasePrice * $stockIncrease,
                'total_cost'  => $purchasePrice * $stockIncrease,
                'quantity'    => $stockIncrease,
                'category'    => 'products',
                'note'        => "Auto-created from stock increase (+{$stockIncrease} units, unit cost: {$purchasePrice} MAD)",
                'expense_date' => now()->toDateString(),
                'created_by'  => $request->user()->id,
            ]);
        }

        $product->load('images');

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product
        ]);
    }

    // =========================
    // ADMIN: GET ALL PRODUCTS (includes out-of-stock, unlike public index)
    // =========================
    public function adminIndex(Request $request)
    {
        $query = Product::with('category', 'brand', 'images')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by brand
        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // Filter by search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
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

        $perPage = $request->per_page ?? 20;
        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    // =========================
    // CHECK PRODUCT REFERENCES (ADMIN) — before deletion
    // =========================
    public function references($id)
    {
        $product = Product::findOrFail($id);

        $references = [
            'orders'      => $product->orderItems()->count(),
            'invoices'    => $product->orderItems()
                ->whereHas('order.invoices')
                ->count(),
            'reviews'     => $product->reviews()->count(),
            'wishlists'   => $product->wishlists()->count(),
            'carts'       => $product->cart()->count(),
            'expenses'    => Expense::where('product_id', $product->id)->count(),
            'coupons'     => Coupon::whereHas('products', fn($q) => $q->where('products.id', $product->id))->count(),
        ];

        $hasReferences = collect($references)->sum() > 0;

        return response()->json([
            'has_references' => $hasReferences,
            'references'     => $references,
        ]);
    }

    // =========================
    // DELETE PRODUCT (ADMIN) — invalidates product caches
    // =========================
    public function destroy(Request $request, $id)
    {
        app(CacheService::class)->invalidateProducts();
        $product = Product::findOrFail($id);

        // Check for references unless force flag is set
        if (!$request->boolean('force')) {
            $references = [
                'orders'      => $product->orderItems()->count(),
                'invoices'    => $product->orderItems()
                    ->whereHas('order.invoices')
                    ->count(),
                'reviews'     => $product->reviews()->count(),
                'wishlists'   => $product->wishlists()->count(),
                'carts'       => $product->cart()->count(),
                'expenses'    => Expense::where('product_id', $product->id)->count(),
                'coupons'     => Coupon::whereHas('products', fn($q) => $q->where('products.id', $product->id))->count(),
            ];

            $hasReferences = collect($references)->sum() > 0;

            if ($hasReferences) {
                return response()->json([
                    'message'       => 'This product is referenced by existing data and cannot be silently deleted.',
                    'has_references' => true,
                    'references'    => $references,
                ], 409);
            }
        }

        // Delete thumbnail
        if ($product->thumbnail) {
            Storage::disk('public')->delete($product->thumbnail);
        }

        // Delete all product images from storage (cascade handles DB records)
        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->image_url);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully'
        ]);
    }

    // =========================
    // BULK UPDATE STATUS (ADMIN)
    // =========================
    public function bulkStatus(Request $request)
    {
        $request->validate([
            'ids'       => 'required|array',
            'ids.*'     => 'integer|exists:products,id',
            'is_active' => 'required|boolean',
        ]);

        $count = Product::whereIn('id', $request->ids)
            ->update(['is_active' => $request->boolean('is_active')]);

        app(CacheService::class)->invalidateProducts();

        return response()->json([
            'message' => "{$count} product(s) updated successfully.",
            'count'   => $count,
        ]);
    }

    // =========================
    // DELETE SINGLE PRODUCT IMAGE (ADMIN)
    // =========================
    public function deleteImage($productId, $imageId)
    {
        $product = Product::findOrFail($productId);
        $image = ProductImage::where('product_id', $product->id)->findOrFail($imageId);

        Storage::disk('public')->delete($image->image_url);
        $image->delete();

        return response()->json([
            'message' => 'Image deleted successfully'
        ]);
    }

    // =========================
    // UPLOAD SINGLE IMAGE (ADMIN)
    // =========================
    public function uploadImage(Request $request, string $fieldName, string $path): ?string
    {
        if ($request->hasFile($fieldName)) {
            return $request->file($fieldName)->store($path, 'public');
        }
        return $request->input($fieldName); // fallback to string URL
    }
}
