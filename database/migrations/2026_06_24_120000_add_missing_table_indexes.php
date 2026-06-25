<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add remaining indexes for categories, brands, and addresses.
     *
     * Some indexes from the previous migration (2026_06_24_110000) were lost
     * during an incomplete rollback — this fills those gaps without attempting
     * to drop any existing indexes, which avoids FK constraint conflicts.
     */
    public function up(): void
    {
        // ── Categories ──────────────────────────────────────────
        Schema::table('categories', function (Blueprint $table) {
            // Skip is_active — already exists from previous migration.
            // These are missing after the partial rollback:
            $table->index('name',           'categories_name_index');
            $table->index(['is_active', 'name'], 'categories_active_name_index');
        });

        // ── Brands ──────────────────────────────────────────────
        Schema::table('brands', function (Blueprint $table) {
            $table->index('name',           'brands_name_index');
            $table->index(['is_active', 'name'], 'brands_active_name_index');
        });

        // ── Addresses ───────────────────────────────────────────
        Schema::table('addresses', function (Blueprint $table) {
            // user_id FK index already exists from constrained().
            // (user_id, created_at) already exists from previous migration.
            // These are missing after the partial rollback:
            $table->index('is_default',                     'addresses_is_default_index');
            $table->index('city',                           'addresses_city_index');
            $table->index(['user_id', 'is_default'],         'addresses_user_default_index');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_name_index');
            $table->dropIndex('categories_active_name_index');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropIndex('brands_name_index');
            $table->dropIndex('brands_active_name_index');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex('addresses_is_default_index');
            $table->dropIndex('addresses_city_index');
            $table->dropIndex('addresses_user_default_index');
        });
    }
};
