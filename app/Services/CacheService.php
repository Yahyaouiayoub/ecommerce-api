<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Centralized caching service for ecommerce data.
 *
 * Provides consistent TTLs, cache key generation, and invalidation
 * for product listings, categories, and public settings.
 *
 * Uses a cache-version-based invalidation strategy for product listing
 * caches so that any cache driver (database, file, redis, etc.) can
 * be supported. Tags are NOT used because the "database" driver does
 * not support them.
 */
class CacheService
{
    /**
     * Cache TTLs in seconds.
     */
    public const PRODUCT_LIST_TTL = 300;     // 5 minutes
    public const PRODUCT_DETAIL_TTL = 600;   // 10 minutes
    public const CATEGORY_TTL = 600;         // 10 minutes
    public const SETTINGS_TTL = 900;         // 15 minutes
    public const PRICE_RANGE_TTL = 600;      // 10 minutes

    /**
     * Get the current cache version for product listings.
     * Incremented whenever product caches need invalidation.
     */
    private function getProductCacheVersion(): int
    {
        return (int) Cache::get('products_cache_version', 0);
    }

    /**
     * Increment the product cache version, effectively invalidating
     * all cached product listings that include the version in their key.
     */
    private function incrementProductCacheVersion(): void
    {
        Cache::forever('products_cache_version', $this->getProductCacheVersion() + 1);
    }

    /**
     * Generate a cache key for a product listing request.
     * Includes all filter/sort/pagination params so different views are cached separately.
     * The version prefix allows atomic invalidation without requiring cache tags.
     */
    public function productListKey(Request $request): string
    {
        $params = $request->only([
            'category_id', 'brand_id', 'search', 'sort', 'min_price', 'max_price',
            'new_arrivals', 'best_sellers', 'per_page', 'page',
        ]);
        ksort($params);
        $version = $this->getProductCacheVersion();
        return 'products:list:v' . $version . ':' . md5(json_encode($params));
    }

    /**
     * Get or remember product listings with caching.
     */
    public function rememberProductList(Request $request, callable $callback): mixed
    {
        $key = $this->productListKey($request);
        return Cache::remember($key, self::PRODUCT_LIST_TTL, $callback);
    }

    /**
     * Get or remember best sellers / featured products (small, fixed queries).
     */
    public function rememberProductCollection(string $type, callable $callback): mixed
    {
        return Cache::remember("products:{$type}", self::PRODUCT_DETAIL_TTL, $callback);
    }

    /**
     * Get or remember a single product detail.
     */
    public function rememberProductDetail(int|string $identifier, callable $callback): mixed
    {
        return Cache::remember("products:detail:{$identifier}", self::PRODUCT_DETAIL_TTL, $callback);
    }

    /**
     * Get or remember category list.
     */
    public function rememberCategories(callable $callback): mixed
    {
        return Cache::remember('categories:active', self::CATEGORY_TTL, $callback);
    }

    /**
     * Get or remember admin category list.
     */
    public function rememberAdminCategories(callable $callback): mixed
    {
        return Cache::remember('categories:admin', self::CATEGORY_TTL, $callback);
    }

    /**
     * Get or remember public settings.
     */
    public function rememberPublicSettings(callable $callback): mixed
    {
        return Cache::remember('settings:public', self::SETTINGS_TTL, $callback);
    }

    /**
     * Get or remember admin settings.
     */
    public function rememberAdminSettings(callable $callback): mixed
    {
        return Cache::remember('settings:admin', self::SETTINGS_TTL, $callback);
    }

    /**
     * Get or remember product price range.
     */
    public function rememberPriceRange(callable $callback): mixed
    {
        return Cache::remember('products:price-range', self::PRICE_RANGE_TTL, $callback);
    }

    // =========================
    // INVALIDATION
    // =========================

    /**
     * Invalidate all product listing caches.
     * Called after creating, updating, or deleting a product.
     *
     * Uses cache versioning instead of tags so that any cache driver
     * (database, file, redis, etc.) is supported.
     */
    public function invalidateProducts(): void
    {
        $this->incrementProductCacheVersion();

        // Also clear the fixed product collection caches
        Cache::forget('products:best-sellers');
        Cache::forget('products:featured');
    }

    /**
     * Invalidate a specific product detail cache.
     */
    public function invalidateProductDetail(int $productId, string $slug): void
    {
        Cache::forget("products:detail:{$productId}");
        Cache::forget("products:detail:{$slug}");
        Cache::forget("products:detail:{$slug}-{$productId}");
    }

    /**
     * Invalidate category caches.
     * Called after creating, updating, or deleting a category.
     */
    public function invalidateCategories(): void
    {
        Cache::forget('categories:active');
        Cache::forget('categories:admin');
        // Product listings include category filters, so invalidate those too
        $this->invalidateProducts();
    }

    /**
     * Invalidate public settings cache.
     * Called after updating settings.
     */
    public function invalidateSettings(): void
    {
        Cache::forget('settings:public');
        Cache::forget('settings:admin');
    }

    /**
     * Invalidate price range cache.
     */
    public function invalidatePriceRange(): void
    {
        Cache::forget('products:price-range');
    }

    /**
     * Invalidate everything related to products (listings + detail + price range).
     *
     * Uses cache versioning for listing caches and individual forget()
     * for fixed-key caches so any driver is supported.
     */
    public function invalidateAllProductCaches(): void
    {
        $this->incrementProductCacheVersion();

        // Clear fixed product collection caches
        Cache::forget('products:best-sellers');
        Cache::forget('products:featured');

        // Clear price range
        Cache::forget('products:price-range');

        // Individual product detail keys cannot be enumerated here,
        // but they have moderate TTLs and will expire naturally.
        // Call invalidateProductDetail() when a specific product changes.
    }
}
