<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
            $table->string('name_fr')->nullable()->after('name_en');
            $table->string('name_ar')->nullable()->after('name_fr');
            $table->string('name_es')->nullable()->after('name_ar');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'name_fr', 'name_ar', 'name_es']);
        });
    }
};
