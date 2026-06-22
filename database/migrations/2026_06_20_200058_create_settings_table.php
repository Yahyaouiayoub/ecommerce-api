<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        // Seed default settings
        DB::table('settings')->insert([
            // Shipping settings
            ['key' => 'shipping_enabled',          'value' => '1',            'group' => 'shipping'],
            ['key' => 'free_shipping_enabled',     'value' => '1',            'group' => 'shipping'],
            ['key' => 'free_shipping_min_amount',  'value' => '75',           'group' => 'shipping'],
            ['key' => 'standard_shipping_cost',    'value' => '8',            'group' => 'shipping'],
            ['key' => 'shipping_message',          'value' => 'On all orders over $75, delivered fast.', 'group' => 'shipping'],
            // Tax settings
            ['key' => 'tax_enabled',               'value' => '1',            'group' => 'tax'],
            ['key' => 'tax_rate',                  'value' => '8',            'group' => 'tax'],
            ['key' => 'tax_type',                  'value' => 'percentage',   'group' => 'tax'],
            ['key' => 'tax_label',                 'value' => 'Estimated tax', 'group' => 'tax'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
