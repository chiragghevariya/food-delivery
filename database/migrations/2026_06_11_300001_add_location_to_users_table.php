<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the rider's live location to the users table.
     * (The rider app updates these every few seconds while delivering.)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('is_active');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->timestamp('location_updated_at')->nullable()->after('lng');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng', 'location_updated_at']);
        });
    }
};
