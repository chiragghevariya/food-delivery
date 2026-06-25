<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link orders to the payouts that settled them.
     *
     * A delivered order is settled TWICE and independently:
     *   - restaurant_payout_id → the payout that paid the restaurant its subtotal
     *   - rider_payout_id      → the payout that paid the rider its delivery fee
     *
     * Generation only ever picks orders whose relevant link is still NULL, so a
     * payout can never count the same order twice.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('restaurant_payout_id')->nullable()->after('rider_id')
                ->constrained('payouts')->nullOnDelete();
            $table->foreignId('rider_payout_id')->nullable()->after('restaurant_payout_id')
                ->constrained('payouts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restaurant_payout_id');
            $table->dropConstrainedForeignId('rider_payout_id');
        });
    }
};
