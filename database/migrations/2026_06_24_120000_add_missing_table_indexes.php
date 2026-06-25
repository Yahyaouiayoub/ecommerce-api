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
     *
     * On a fresh database the indexes already exist, so we skip them.
     */
    public function up(): void
    {
        // ── Categories ──────────────────────────────────────────
        Schema::table('categories', function (Blueprint $table) {
            if (!$this->indexExists('categories', 'categories_name_index')) {
                $table->index('name',           'categories_name_index');
            }
            if (!$this->indexExists('categories', 'categories_active_name_index')) {
                $table->index(['is_active', 'name'], 'categories_active_name_index');
            }
        });

        // ── Brands ──────────────────────────────────────────────
        Schema::table('brands', function (Blueprint $table) {
            if (!$this->indexExists('brands', 'brands_name_index')) {
                $table->index('name',           'brands_name_index');
            }
            if (!$this->indexExists('brands', 'brands_active_name_index')) {
                $table->index(['is_active', 'name'], 'brands_active_name_index');
            }
        });

        // ── Addresses ───────────────────────────────────────────
        Schema::table('addresses', function (Blueprint $table) {
            if (!$this->indexExists('addresses', 'addresses_is_default_index')) {
                $table->index('is_default',                     'addresses_is_default_index');
            }
            if (!$this->indexExists('addresses', 'addresses_city_index')) {
                $table->index('city',                           'addresses_city_index');
            }
            if (!$this->indexExists('addresses', 'addresses_user_default_index')) {
                $table->index(['user_id', 'is_default'],         'addresses_user_default_index');
            }
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

    /**
     * Check whether a named index already exists on the given table.
     * Works on both SQLite and MySQL.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $result = $connection->select(
                "SELECT name FROM sqlite_master WHERE type='index' AND name = ? AND tbl_name = ?",
                [$indexName, $table]
            );
            return !empty($result);
        }

        // MySQL / MariaDB
        $result = $connection->select(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?",
            [$connection->getDatabaseName(), $table, $indexName]
        );
        return !empty($result);
    }
};
