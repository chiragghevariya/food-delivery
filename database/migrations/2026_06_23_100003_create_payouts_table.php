<?php

use App\Enums\PayoutStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payouts owed to a payee for a given period.
     *
     * The payee is polymorphic so the same table covers:
     *   - restaurants  (paid: order subtotals minus platform commission)
     *   - riders/users (paid: delivery fees they earned)
     */
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();

            // who we are paying (Restaurant or User-as-rider)
            $table->morphs('payee'); // payee_type + payee_id (+ index)

            // the period this payout covers
            $table->date('period_start');
            $table->date('period_end');

            // how it was calculated
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('gross_amount', 12, 2)->default(0);      // earnings before commission
            $table->decimal('commission_amount', 12, 2)->default(0); // platform cut (restaurants)
            $table->decimal('net_amount', 12, 2)->default(0);        // what we actually pay

            $table->enum('status', PayoutStatus::values())
                ->default(PayoutStatus::PENDING->value)
                ->index();

            $table->timestamp('paid_at')->nullable();
            $table->string('reference')->nullable(); // bank/txn reference once paid
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
