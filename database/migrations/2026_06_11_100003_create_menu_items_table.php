<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the "menu_items" table (the dishes a restaurant sells).
     */
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            // belongs to a restaurant; delete its items if the restaurant is deleted
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            // belongs to a category; if the category is deleted, just set this to null
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2);                 // e.g. 12.50
            $table->string('image')->nullable();
            $table->boolean('is_available')->default(true); // can it be ordered right now?
            $table->timestamps();
        });
    }

    /**
     * Drop the table when rolling back.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
