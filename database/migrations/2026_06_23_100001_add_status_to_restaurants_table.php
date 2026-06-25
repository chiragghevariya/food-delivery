<?php

use App\Enums\RestaurantStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a moderation status to restaurants so the super-admin can
     * approve / reject new sign-ups before they go live to customers.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->enum('status', RestaurantStatus::values())
                ->default(RestaurantStatus::PENDING->value)
                ->after('rating')
                ->index();
            $table->timestamp('approved_at')->nullable()->after('status');
        });

        // Existing/demo restaurants are already live — mark them approved so
        // they don't disappear from the customer app after this migration.
        DB::table('restaurants')->update([
            'status'      => RestaurantStatus::APPROVED->value,
            'approved_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['status', 'approved_at']);
        });
    }
};
