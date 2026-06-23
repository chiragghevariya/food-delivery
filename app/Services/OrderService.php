<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Holds the business logic for creating orders.
 * Keeping it here (instead of in the controller) keeps the controller thin
 * and lets us reuse this logic from other places later.
 */
class OrderService
{
    /** Flat delivery fee charged on every order (in dollars). */
    public const DELIVERY_FEE = 2.00;

    /**
     * Create a new order from a customer's cart.
     *
     * @param  User   $customer  the logged-in customer placing the order
     * @param  array  $data      validated input: restaurant_id, items[], address, lat, lng, notes
     */
    public function placeOrder(User $customer, array $data): Order
    {
        // Everything runs inside a database transaction: if any step fails,
        // nothing is saved (no half-created orders).
        return DB::transaction(function () use ($customer, $data) {

            // 1) Load the restaurant and make sure it is open.
            $restaurant = Restaurant::findOrFail($data['restaurant_id']);

            if (! $restaurant->is_open) {
                throw ValidationException::withMessages([
                    'restaurant_id' => 'This restaurant is currently closed.',
                ]);
            }

            // 2) Load the menu items in the cart that actually belong to this
            //    restaurant and are available. Keyed by id for fast lookup.
            $itemIds = collect($data['items'])->pluck('menu_item_id');

            $menuItems = MenuItem::where('restaurant_id', $restaurant->id)
                ->where('is_available', true)
                ->whereIn('id', $itemIds)
                ->get()
                ->keyBy('id');

            // 3) Build the order lines and add up the subtotal.
            //    IMPORTANT: prices come from the DATABASE, never from the request,
            //    so a customer can't send a fake price.
            $subtotal       = 0;
            $orderItemsData = [];

            foreach ($data['items'] as $line) {
                $menuItem = $menuItems->get($line['menu_item_id']);

                // If an item isn't valid for this restaurant, reject the whole order.
                if (! $menuItem) {
                    throw ValidationException::withMessages([
                        'items' => "Menu item #{$line['menu_item_id']} is not available for this restaurant.",
                    ]);
                }

                $quantity  = (int) $line['quantity'];
                $unitPrice = (float) $menuItem->price;
                $lineTotal = round($unitPrice * $quantity, 2);

                $subtotal += $lineTotal;

                $orderItemsData[] = [
                    'menu_item_id' => $menuItem->id,
                    'quantity'     => $quantity,
                    'unit_price'   => $unitPrice,
                    'subtotal'     => $lineTotal,
                ];
            }

            // 4) Money: subtotal + flat delivery fee = total.
            $subtotal    = round($subtotal, 2);
            $deliveryFee = self::DELIVERY_FEE;
            $total       = round($subtotal + $deliveryFee, 2);

            // 5) Create the order itself (status starts as "pending").
            $order = Order::create([
                'customer_id'   => $customer->id,
                'restaurant_id' => $restaurant->id,
                'status'        => OrderStatus::PENDING,
                'subtotal'      => $subtotal,
                'delivery_fee'  => $deliveryFee,
                'total'         => $total,
                'address'       => $data['address'],
                'lat'           => $data['lat'] ?? null,
                'lng'           => $data['lng'] ?? null,
                'notes'         => $data['notes'] ?? null,
            ]);

            // 6) Save all the order items in one go.
            $order->items()->createMany($orderItemsData);

            // 7) Write the first entry in the status history log.
            $order->statusHistory()->create([
                'status' => OrderStatus::PENDING->value,
                'note'   => 'Order placed by customer.',
            ]);

            // Return the order with everything the API needs to display it.
            return $order->load(['items.menuItem', 'statusHistory', 'restaurant']);
        });
    }
}
