<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the "restaurants" table. Each restaurant belongs to one owner (a user).
     */
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            // links to users.id; if the owner is deleted, delete their restaurant too
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();   // latitude  (map location)
            $table->decimal('lng', 10, 7)->nullable();   // longitude (map location)
            $table->string('logo')->nullable();
            $table->string('banner')->nullable();
            $table->boolean('is_open')->default(true);   // is the restaurant currently accepting orders?
            $table->decimal('rating', 3, 2)->default(0); // average rating 0.00 - 5.00
            $table->timestamps();
        });
    }

    /**
     * Drop the table when rolling back.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
