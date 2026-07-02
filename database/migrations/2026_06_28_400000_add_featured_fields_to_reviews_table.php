<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('comment');
            $table->boolean('is_featured_active')->default(true)->after('is_featured');
            $table->unsignedSmallInteger('featured_order')->default(0)->after('is_featured_active');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['is_featured', 'is_featured_active', 'featured_order']);
        });
    }
};
