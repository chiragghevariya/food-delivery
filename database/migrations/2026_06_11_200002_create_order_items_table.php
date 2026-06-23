<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the "order_items" table (one row per product line in an order).
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            // the order this line belongs to; delete the lines if the order is deleted
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // the menu item ordered. We keep the row even if the menu item is later
            // deleted (set to null), so old orders still show their history.
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2); // price of ONE item at the moment of ordering
            $table->decimal('subtotal', 10, 2);   // unit_price * quantity

            $table->timestamps();
        });
    }

    /**
     * Drop the table when rolling back.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
