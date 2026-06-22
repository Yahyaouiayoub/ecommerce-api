<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support altering ENUMs, so we recreate the column
        // For MySQL/PostgreSQL this would use a different approach
        
        // Drop the old column and recreate with new statuses
        Schema::table('invoices', function (Blueprint $table) {
            // First drop old status column
            $table->dropColumn('status');
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Add new status column
            $table->string('status', 20)->default('unpaid')->after('paid_amount');
        });

        // Also add billing fields for customer info
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('billing_name')->nullable()->after('notes');
            $table->string('billing_email')->nullable()->after('billing_name');
            $table->string('billing_phone')->nullable()->after('billing_email');
            $table->text('billing_address')->nullable()->after('billing_phone');
            $table->string('payment_method', 50)->nullable()->after('billing_address');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['billing_name', 'billing_email', 'billing_phone', 'billing_address', 'payment_method']);
        });

        // Restore old enum
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('status', ['unpaid', 'partially_paid', 'paid'])->default('unpaid')->after('paid_amount');
        });
    }
};
