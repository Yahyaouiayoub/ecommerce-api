<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('cost', 10, 2)->default(0);
            $table->integer('estimated_days')->nullable()->comment('Estimated delivery in days');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed a default standard shipping method
        DB::table('shipping_methods')->insert([
            'name'           => 'Standard Shipping',
            'description'    => 'Delivered within 5-7 business days',
            'cost'           => 8.00,
            'estimated_days' => 7,
            'sort_order'     => 0,
            'is_active'      => true,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
