<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('refund_number')->unique();
            $table->string('status')->default('pending'); // pending, approved, rejected, completed
            $table->string('reason')->nullable();
            $table->text('description')->nullable();
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->text('internal_notes')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_name')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        Schema::create('refund_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->timestamps();
        });

        // Add refund columns to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->string('refund_status')->nullable()->after('discount_type');
            $table->decimal('refund_amount', 12, 2)->default(0)->after('refund_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['refund_status', 'refund_amount']);
        });

        Schema::dropIfExists('refund_images');
        Schema::dropIfExists('refund_items');
        Schema::dropIfExists('refunds');
    }
};
