<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add database indexes for brands, categories, and addresses tables.
     *
     * Categories & Brands:
     *   - is_active: frequently filtered when displaying active records
     *   - name: used for alphabetical sorting and search
     *   - Composite (is_active, name): optimises sorted active listings
     *
     * Addresses:
     *   - user_id FK already has an index via constrained()
     *   - is_default: used in ORDER BY and WHERE when managing defaults
     *   - Composite (user_id, is_default): for "unset all other defaults" queries
     *   - city: used for search/filter in admin panels
     */
    public function up(): void
    {
        // ── Categories ──────────────────────────────────────────
        // Note: composite (is_active, name) covers the leftmost prefix
        // (is_active) so a standalone is_active index is not needed.
        Schema::table('categories', function (Blueprint $table) {
            $table->index('name',           'categories_name_index');
            $table->index(['is_active', 'name'], 'categories_active_name_index');
        });

        // ── Brands ──────────────────────────────────────────────
        // Note: composite (is_active, name) covers the leftmost prefix
        // (is_active) so a standalone is_active index is not needed.
        Schema::table('brands', function (Blueprint $table) {
            $table->index('name',           'brands_name_index');
            $table->index(['is_active', 'name'], 'brands_active_name_index');
        });

        // ── Addresses ───────────────────────────────────────────
        Schema::table('addresses', function (Blueprint $table) {
            $table->index('is_default',     'addresses_is_default_index');
            $table->index('city',           'addresses_city_index');
            $table->index(['user_id', 'is_default'], 'addresses_user_default_index');
            $table->index(['user_id', 'created_at'], 'addresses_user_created_index');
        });
    }

    public function down(): void
    {
        // Categories
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_name_index');
            $table->dropIndex('categories_active_name_index');
        });

        // Brands
        Schema::table('brands', function (Blueprint $table) {
            $table->dropIndex('brands_name_index');
            $table->dropIndex('brands_active_name_index');
        });

        // Addresses
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex('addresses_is_default_index');
            $table->dropIndex('addresses_city_index');
            $table->dropIndex('addresses_user_default_index');
            $table->dropIndex('addresses_user_created_index');
        });
    }
};
