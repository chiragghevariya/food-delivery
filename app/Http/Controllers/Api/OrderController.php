<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoints for a customer to place and view their orders.
 * All routes here are protected by the "auth:api" middleware.
 */
class OrderController extends Controller
{
    // Laravel automatically creates and injects the OrderService for us.
    public function __construct(private OrderService $orderService) {}

    /**
     * POST /api/orders/payment-intent
     * Get a Stripe PaymentIntent client secret for checkout.
     */
    public function paymentIntent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'restaurant_id'        => ['required', 'integer', 'exists:restaurants,id'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.quantity'     => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        try {
            $result = $this->orderService->createPaymentIntent(auth('api')->user(), $data);
            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create payment intent: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * POST /api/orders
     * Place a new order from the customer's cart.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'restaurant_id'        => ['required', 'integer', 'exists:restaurants,id'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.quantity'     => ['required', 'integer', 'min:1', 'max:99'],
            'address'              => ['required', 'string', 'max:500'],
            'lat'                  => ['nullable', 'numeric', 'between:-90,90'],
            'lng'                  => ['nullable', 'numeric', 'between:-180,180'],
            'notes'                => ['nullable', 'string', 'max:1000'],
            'payment_intent_id'    => ['nullable', 'string', 'max:255'],
        ]);

        // auth('api')->user() is the logged-in customer.
        $order = $this->orderService->placeOrder(auth('api')->user(), $data);

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED); // 201
    }

    /**
     * GET /api/orders
     * List the logged-in customer's own orders (newest first).
     */
    public function index(): JsonResponse
    {
        $orders = Order::where('customer_id', auth('api')->id())
            ->with(['items.menuItem', 'restaurant'])
            ->latest()
            ->paginate(15);

        return OrderResource::collection($orders)->response();
    }

    /**
     * GET /api/orders/{order}
     * Show one order with its items and status history.
     */
    public function show(Order $order): JsonResponse
    {
        $this->authorizeOwner($order);

        $order->load(['items.menuItem', 'statusHistory', 'restaurant', 'rider']);

        return (new OrderResource($order))->response();
    }

    /**
     * POST /api/orders/{order}/cancel
     * Let the customer cancel their order, but only while it is still "pending".
     */
    public function cancel(Order $order): JsonResponse
    {
        $this->authorizeOwner($order);

        // Only a pending order can be cancelled by the customer.
        if ($order->status !== OrderStatus::PENDING) {
            return response()->json([
                'message' => 'This order can no longer be cancelled.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY); // 422
        }

        // change the status to "cancelled" AND log it (see Order::changeStatus)
        $order->changeStatus(OrderStatus::CANCELLED, 'Cancelled by customer.');

        $order->load(['items.menuItem', 'statusHistory', 'restaurant']);

        return (new OrderResource($order))->response();
    }

    /**
     * Stop a user from viewing or cancelling someone else's order.
     */
    private function authorizeOwner(Order $order): void
    {
        abort_unless(
            $order->customer_id === auth('api')->id(),
            Response::HTTP_FORBIDDEN,
            'This order does not belong to you.'
        );
    }
}
