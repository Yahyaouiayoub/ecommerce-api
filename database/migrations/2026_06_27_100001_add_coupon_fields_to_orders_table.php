<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('coupon_code', 50)->nullable()->after('notes');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('coupon_code');
            $table->string('discount_type', 20)->nullable()->after('discount_amount'); // percentage | fixed
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['coupon_code', 'discount_amount', 'discount_type']);
        });
    }
};
