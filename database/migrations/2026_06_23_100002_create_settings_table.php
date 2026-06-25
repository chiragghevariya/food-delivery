<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A simple key/value store for global platform configuration
     * (commission %, default delivery fee, currency, etc.).
     * Read/written through the App\Models\Setting helper.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string|integer|float|boolean|json
            $table->string('group')->default('general'); // for grouping on the settings page
            $table->timestamps();
        });

        // Sensible defaults so the platform works out of the box.
        $now = now();
        DB::table('settings')->insert([
            ['key' => 'platform_commission_rate', 'value' => '15',    'type' => 'float',   'group' => 'finance', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'default_delivery_fee',     'value' => '2.50',  'type' => 'float',   'group' => 'finance', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'currency',                 'value' => 'USD',   'type' => 'string',  'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'currency_symbol',          'value' => '$',     'type' => 'string',  'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'min_order_value',          'value' => '5.00',  'type' => 'float',   'group' => 'finance', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'maintenance_mode',         'value' => '0',     'type' => 'boolean', 'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
