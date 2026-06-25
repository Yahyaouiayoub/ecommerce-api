<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)->default(0)->after('stock');
            $table->decimal('margin_percentage', 5, 2)->default(0)->after('purchase_price');
            $table->decimal('final_price', 10, 2)->default(0)->after('margin_percentage');
            $table->decimal('discount_price', 10, 2)->nullable()->after('final_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['purchase_price', 'margin_percentage', 'final_price', 'discount_price']);
        });
    }
};
