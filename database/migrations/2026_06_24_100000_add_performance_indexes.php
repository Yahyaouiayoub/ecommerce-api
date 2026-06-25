<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add database indexes to columns frequently queried by the dashboard
     * and other high-traffic endpoints.
     *
     * These indexes dramatically speed up:
     *   - Dashboard stats & financial queries (invoices, orders, products, expenses)
     *   - Product listing / filtering  (category_id, brand_id, price, stock, is_active)
     *   - Order management             (status, created_at, user_id)
     *   - Expense reporting            (expense_date, category)
     */
    public function up(): void
    {
        // ── Products ──────────────────────────────────────────
        Schema::table('products', function (Blueprint $table) {
            $table->index('is_active',       'products_is_active_index');
            $table->index('featured',        'products_featured_index');
            $table->index('price',           'products_price_index');
            $table->index('stock',           'products_stock_index');
            $table->index('created_at',      'products_created_at_index');
            // Composite: filtering active products in a category
            $table->index(['category_id', 'is_active'], 'products_cat_active_index');
        });

        // ── Orders ────────────────────────────────────────────
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status',          'orders_status_index');
            $table->index('created_at',      'orders_created_at_index');
            $table->index('user_id',         'orders_user_id_index');
            // Composite: dashboard "recent orders by status"
            $table->index(['status', 'created_at'], 'orders_status_created_index');
        });

        // ── Invoices ──────────────────────────────────────────
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('status',          'invoices_status_index');
            $table->index('paid_at',         'invoices_paid_at_index');
            $table->index('created_at',      'invoices_created_at_index');
            // Composite: revenue aggregation (status + paid_at)
            $table->index(['status', 'paid_at'], 'invoices_status_paid_index');
        });

        // ── Expenses ──────────────────────────────────────────
        Schema::table('expenses', function (Blueprint $table) {
            $table->index('expense_date',    'expenses_date_index');
            $table->index('category',        'expenses_category_index');
            // Composite: monthly reporting
            $table->index(['expense_date', 'category'], 'expenses_date_cat_index');
        });

        // ── Order Items (for best-sellers / popular queries) ──
        Schema::table('order_items', function (Blueprint $table) {
            $table->index('product_id',      'order_items_product_id_index');
        });

        // ── Reviews ───────────────────────────────────────────
        Schema::table('reviews', function (Blueprint $table) {
            $table->index('rating',          'reviews_rating_index');
        });

        // ── Payments ──────────────────────────────────────────
        Schema::table('payments', function (Blueprint $table) {
            $table->index('status',          'payments_status_index');
            $table->index('paid_at',         'payments_paid_at_index');
            $table->index('invoice_id',      'payments_invoice_id_index');
        });
    }

    public function down(): void
    {
        // Products
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_is_active_index');
            $table->dropIndex('products_featured_index');
            $table->dropIndex('products_price_index');
            $table->dropIndex('products_stock_index');
            $table->dropIndex('products_created_at_index');
            $table->dropIndex('products_cat_active_index');
        });

        // Orders
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_index');
            $table->dropIndex('orders_created_at_index');
            $table->dropIndex('orders_user_id_index');
            $table->dropIndex('orders_status_created_index');
        });

        // Invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_status_index');
            $table->dropIndex('invoices_paid_at_index');
            $table->dropIndex('invoices_created_at_index');
            $table->dropIndex('invoices_status_paid_index');
        });

        // Expenses
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_date_index');
            $table->dropIndex('expenses_category_index');
            $table->dropIndex('expenses_date_cat_index');
        });

        // Order Items
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_product_id_index');
        });

        // Reviews
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_rating_index');
        });

        // Payments
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_status_index');
            $table->dropIndex('payments_paid_at_index');
            $table->dropIndex('payments_invoice_id_index');
        });
    }
};
