<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the "orders" table.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // who placed the order (a customer = a row in the users table)
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            // which restaurant the order is for
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            // which rider delivers it (empty until a rider accepts the job)
            $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();

            // the current status; only the values from our OrderStatus enum are allowed
            $table->enum('status', OrderStatus::values())->default(OrderStatus::PENDING->value);

            // money (decimal = exact, 2 places after the dot)
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            // where to deliver
            $table->text('address');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            // optional note from the customer ("leave at the door", etc.)
            $table->text('notes')->nullable();

            $table->timestamps();

            // we frequently filter orders by status, so add an index
            $table->index('status');
        });
    }

    /**
     * Drop the table when rolling back.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
