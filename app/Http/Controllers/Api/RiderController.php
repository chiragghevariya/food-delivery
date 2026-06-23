<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\FCMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoints used by the RIDER app.
 * Every route here is protected by:  auth:api + role:rider,api
 * (see routes/api.php) — only logged-in riders can use them.
 */
class RiderController extends Controller
{
    // Laravel injects the push-notification service automatically.
    public function __construct(private FCMService $fcm) {}

    /**
     * GET /api/rider/orders/available
     * Orders waiting for a rider (status = ready_for_pickup, no rider yet).
     * Optionally pass ?lat=&lng=&radius= to only see orders near the rider.
     */
    public function available(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat'    => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng'    => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'numeric', 'min:0.1', 'max:50'], // km
        ]);

        // Base list: ready for pickup and not yet taken by any rider.
        $orders = Order::query()
            ->where('status', OrderStatus::READY_FOR_PICKUP)
            ->whereNull('rider_id')
            ->with(['restaurant', 'items.menuItem'])
            ->latest()
            ->get();

        // If the rider sent their location, keep only nearby orders (measured to
        // the restaurant pickup point) and sort them closest-first.
        if (isset($data['lat'], $data['lng'])) {
            $lat    = (float) $data['lat'];
            $lng    = (float) $data['lng'];
            $radius = (float) ($data['radius'] ?? 10);

            $orders = $orders
                ->filter(fn (Order $o) => $o->restaurant && $o->restaurant->lat !== null && $o->restaurant->lng !== null)
                ->map(function (Order $o) use ($lat, $lng) {
                    $o->distance_km = round(
                        $this->distanceInKm($lat, $lng, (float) $o->restaurant->lat, (float) $o->restaurant->lng),
                        2
                    );
                    return $o;
                })
                ->filter(fn (Order $o) => $o->distance_km <= $radius)
                ->sortBy('distance_km')
                ->values();
        }

        return OrderResource::collection($orders)->response();
    }

    /**
     * POST /api/rider/orders/{order}/accept
     * The rider claims an available order (sets rider_id).
     */
    public function accept(Order $order): JsonResponse
    {
        $rider = auth('api')->user();

        // Atomically claim the order: this UPDATE only succeeds if the order is
        // still ready_for_pickup AND has no rider — so two riders can't grab the
        // same order at the same time.
        $claimed = Order::where('id', $order->id)
            ->where('status', OrderStatus::READY_FOR_PICKUP)
            ->whereNull('rider_id')
            ->update(['rider_id' => $rider->id]);

        if (! $claimed) {
            return response()->json([
                'message' => 'This order is no longer available to accept.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422
        }

        $order->refresh(); // reload with the new rider_id

        // Record the event (status stays "ready_for_pickup" until actual pickup).
        $order->statusHistory()->create([
            'status' => $order->status->value,
            'note'   => "Rider {$rider->name} accepted the delivery.",
        ]);

        // Tell the customer a rider is coming.
        $order->load('customer');
        $this->fcm->notifyRiderAssigned($order);

        return $this->respondWithOrder($order);
    }

    /**
     * POST /api/rider/orders/{order}/picked-up
     * The rider collected the food from the restaurant.
     */
    public function pickedUp(Order $order): JsonResponse
    {
        $this->authorizeRider($order);

        $rider = auth('api')->user();

        // ready_for_pickup -> picked_up. transitionTo() blocks invalid jumps (422).
        $order->transitionTo(OrderStatus::PICKED_UP, "Picked up by rider {$rider->name}.");

        $order->load('customer');
        $this->fcm->notifyOrderPickedUp($order);

        return $this->respondWithOrder($order);
    }

    /**
     * POST /api/rider/orders/{order}/delivered
     * The rider delivered the order to the customer.
     */
    public function delivered(Order $order): JsonResponse
    {
        $this->authorizeRider($order);

        $rider = auth('api')->user();

        // picked_up -> delivered
        $order->transitionTo(OrderStatus::DELIVERED, "Delivered by rider {$rider->name}.");

        $order->load('customer');
        $this->fcm->notifyOrderDelivered($order);

        return $this->respondWithOrder($order);
    }

    /**
     * GET /api/rider/orders/history
     * The rider's completed (delivered) orders, newest first.
     */
    public function history(): JsonResponse
    {
        $orders = Order::where('rider_id', auth('api')->id())
            ->where('status', OrderStatus::DELIVERED)
            ->with(['restaurant', 'items.menuItem'])
            ->latest()
            ->paginate(15);

        return OrderResource::collection($orders)->response();
    }

    /**
     * POST /api/rider/location
     * The rider app calls this every few seconds while delivering, so the
     * rider's live position is saved (handy for live tracking later).
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        auth('api')->user()->update([
            'lat'                 => $data['lat'],
            'lng'                 => $data['lng'],
            'location_updated_at' => now(),
        ]);

        return response()->json(['message' => 'Location updated.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Small helpers
    |--------------------------------------------------------------------------
    */

    /** Make sure the order is assigned to the logged-in rider (else 403). */
    private function authorizeRider(Order $order): void
    {
        abort_unless(
            $order->rider_id === auth('api')->id(),
            Response::HTTP_FORBIDDEN,
            'This order is not assigned to you.'
        );
    }

    /** Return one order with all the details the rider app needs. */
    private function respondWithOrder(Order $order): JsonResponse
    {
        $order->load(['items.menuItem', 'statusHistory', 'restaurant']);

        return (new OrderResource($order))->response();
    }

    /** Haversine distance in km between two lat/lng points. */
    private function distanceInKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
