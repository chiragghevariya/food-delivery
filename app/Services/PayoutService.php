<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Restaurant;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for all payout/earnings calculations.
 *
 * Restaurant earnings come from the order SUBTOTAL (food), minus the platform
 * commission. Rider earnings come from the DELIVERY FEE (no commission taken).
 * Only DELIVERED orders ever earn, and an order is settled at most once per side
 * (tracked by orders.restaurant_payout_id / orders.rider_payout_id).
 */
class PayoutService
{
    /** Current platform commission percentage (e.g. 15.0). */
    public function commissionRate(): float
    {
        return (float) Setting::get('platform_commission_rate', 15);
    }

    /**
     * Summarise a restaurant's UNSETTLED delivered earnings (nothing is written).
     *
     * @return array{orders_count:int, gross:float, commission:float, net:float, period_start:?string, period_end:?string}
     */
    public function restaurantUnsettledSummary(Restaurant $restaurant): array
    {
        $base = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('status', OrderStatus::DELIVERED)
            ->whereNull('restaurant_payout_id');

        $agg = (clone $base)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(subtotal),0) as gross, MIN(created_at) as first, MAX(created_at) as last')
            ->first();

        return $this->buildSummary(
            count: (int) $agg->cnt,
            gross: (float) $agg->gross,
            withCommission: true,
            first: $agg->first,
            last: $agg->last,
        );
    }

    /**
     * Lifetime earnings totals for a restaurant (all delivered orders, settled or not).
     *
     * @return array{orders_count:int, gross:float, commission:float, net:float}
     */
    public function restaurantLifetimeSummary(Restaurant $restaurant): array
    {
        $agg = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('status', OrderStatus::DELIVERED)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(subtotal),0) as gross')
            ->first();

        $summary = $this->buildSummary((int) $agg->cnt, (float) $agg->gross, withCommission: true);

        return [
            'orders_count' => $summary['orders_count'],
            'gross'        => $summary['gross'],
            'commission'   => $summary['commission'],
            'net'          => $summary['net'],
        ];
    }

    /**
     * Generate one pending payout per restaurant that has unsettled delivered
     * orders, stamping those orders so they can never be paid out again.
     * Wrapped in a transaction so the orders and the payout stay consistent.
     *
     * @return Collection<int, Payout>
     */
    public function generateRestaurantPayouts(): Collection
    {
        $restaurantIds = Order::query()
            ->where('status', OrderStatus::DELIVERED)
            ->whereNull('restaurant_payout_id')
            ->distinct()
            ->pluck('restaurant_id');

        return $restaurantIds
            ->map(fn ($id) => $this->createRestaurantPayout(Restaurant::find($id)))
            ->filter()
            ->values();
    }

    /**
     * Generate one pending payout per rider that has unsettled delivered orders.
     *
     * @return Collection<int, Payout>
     */
    public function generateRiderPayouts(): Collection
    {
        $riderIds = Order::query()
            ->where('status', OrderStatus::DELIVERED)
            ->whereNotNull('rider_id')
            ->whereNull('rider_payout_id')
            ->distinct()
            ->pluck('rider_id');

        return $riderIds
            ->map(fn ($id) => $this->createRiderPayout(User::find($id)))
            ->filter()
            ->values();
    }

    /** Create a restaurant payout from its unsettled orders, or null if none. */
    public function createRestaurantPayout(?Restaurant $restaurant): ?Payout
    {
        if ($restaurant === null) {
            return null;
        }

        return DB::transaction(function () use ($restaurant) {
            $orders = Order::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('status', OrderStatus::DELIVERED)
                ->whereNull('restaurant_payout_id')
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                return null;
            }

            $gross = round((float) $orders->sum('subtotal'), 2);
            $commission = round($gross * $this->commissionRate() / 100, 2);

            $payout = $restaurant->payouts()->create([
                'status'            => PayoutStatus::PENDING,
                'period_start'      => $orders->min('created_at'),
                'period_end'        => $orders->max('created_at'),
                'orders_count'      => $orders->count(),
                'gross_amount'      => $gross,
                'commission_amount' => $commission,
                'net_amount'        => round($gross - $commission, 2),
            ]);

            Order::whereIn('id', $orders->pluck('id'))->update(['restaurant_payout_id' => $payout->id]);

            return $payout;
        });
    }

    /** Create a rider payout from its unsettled orders, or null if none. */
    public function createRiderPayout(?User $rider): ?Payout
    {
        if ($rider === null) {
            return null;
        }

        return DB::transaction(function () use ($rider) {
            $orders = Order::query()
                ->where('rider_id', $rider->id)
                ->where('status', OrderStatus::DELIVERED)
                ->whereNull('rider_payout_id')
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                return null;
            }

            $gross = round((float) $orders->sum('delivery_fee'), 2);

            $payout = $rider->payouts()->create([
                'status'            => PayoutStatus::PENDING,
                'period_start'      => $orders->min('created_at'),
                'period_end'        => $orders->max('created_at'),
                'orders_count'      => $orders->count(),
                'gross_amount'      => $gross,
                'commission_amount' => 0, // riders keep the full delivery fee
                'net_amount'        => $gross,
            ]);

            Order::whereIn('id', $orders->pluck('id'))->update(['rider_payout_id' => $payout->id]);

            return $payout;
        });
    }

    /**
     * @return array{orders_count:int, gross:float, commission:float, net:float, period_start:?string, period_end:?string}
     */
    private function buildSummary(int $count, float $gross, bool $withCommission, $first = null, $last = null): array
    {
        $gross = round($gross, 2);
        $commission = $withCommission ? round($gross * $this->commissionRate() / 100, 2) : 0.0;

        return [
            'orders_count' => $count,
            'gross'        => $gross,
            'commission'   => $commission,
            'net'          => round($gross - $commission, 2),
            'period_start' => $first,
            'period_end'   => $last,
        ];
    }
}
