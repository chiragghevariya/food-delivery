<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the "order_status_history" table.
     * This is an append-only log: every time an order changes status we add a row here.
     */
    public function up(): void
    {
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status');             // one of the OrderStatus values
            $table->string('note')->nullable();   // a short human-friendly message
            // this log only needs a created_at (no updated_at)
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Drop the table when rolling back.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};
