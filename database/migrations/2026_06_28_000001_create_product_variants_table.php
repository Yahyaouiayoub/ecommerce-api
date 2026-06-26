<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. "128GB - Space Black", "Small - Blue"
            $table->decimal('price', 10, 2)->nullable(); // null = use parent product price
            $table->integer('stock')->default(0);
            $table->string('sku')->nullable()->unique();
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->string('storage')->nullable();
            $table->json('attributes')->nullable(); // additional custom attribute key-values
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Application-level enforcement ensures only one default variant per product.
            // Composite unique index on (product_id, is_default) would treat all
            // is_default=0 entries as duplicates in MySQL, so we skip it here.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
