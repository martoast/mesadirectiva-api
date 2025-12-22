<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Event;
use App\Models\EventItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    /**
     * Create Stripe Checkout Session
     * POST /api/checkout/create-session
     */
    public function createSession(CreateCheckoutRequest $request): JsonResponse
    {
        $event = Event::where('slug', $request->event_slug)->firstOrFail();

        if (!$event->canPurchase()) {
            return response()->json([
                'error' => 'Cannot purchase tickets',
                'reason' => $event->getPurchaseBlockedReason(),
            ], 422);
        }

        $requestedTickets = $request->tickets;
        if ($requestedTickets > $event->getTicketsAvailable()) {
            return response()->json([
                'error' => 'Not enough tickets available',
                'available' => $event->getTicketsAvailable(),
            ], 422);
        }

        $extraItems = $request->extra_items ?? [];
        foreach ($extraItems as $extraItem) {
            $item = EventItem::find($extraItem['item_id']);
            if (!$item || !$item->isAvailable()) {
                return response()->json([
                    'error' => "Item '{$item?->name}' is not available",
                ], 422);
            }

            $available = $item->getAvailableQuantity();
            if ($available !== null && $extraItem['quantity'] > $available) {
                return response()->json([
                    'error' => "Not enough '{$item->name}' available",
                    'available' => $available,
                ], 422);
            }
        }

        $subtotal = $event->price * $requestedTickets;
        foreach ($extraItems as $extraItem) {
            $item = EventItem::find($extraItem['item_id']);
            $subtotal += $item->price * $extraItem['quantity'];
        }
        $total = $subtotal;

        $order = DB::transaction(function () use ($event, $request, $requestedTickets, $extraItems, $subtotal, $total) {
            $order = Order::create([
                'event_id' => $event->id,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'total' => $total,
            ]);

            if ($requestedTickets > 0) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'item_type' => 'ticket',
                    'item_name' => $event->name . ' - Ticket',
                    'quantity' => $requestedTickets,
                    'unit_price' => $event->price,
                    'total_price' => $event->price * $requestedTickets,
                ]);
            }

            foreach ($extraItems as $extraItem) {
                $item = EventItem::find($extraItem['item_id']);
                OrderItem::create([
                    'order_id' => $order->id,
                    'item_type' => 'extra_item',
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'quantity' => $extraItem['quantity'],
                    'unit_price' => $item->price,
                    'total_price' => $item->price * $extraItem['quantity'],
                ]);
            }

            return $order;
        });

        $lineItems = $this->stripeService->buildLineItems($event, $requestedTickets, $extraItems);

        $successUrl = config('app.frontend_url') . "/events/{$event->slug}/checkout-success";
        $cancelUrl = config('app.frontend_url') . "/events/{$event->slug}";

        $session = $this->stripeService->createCheckoutSession(
            $event,
            $order,
            $lineItems,
            $successUrl,
            $cancelUrl
        );

        $order->update([
            'stripe_checkout_session_id' => $session->id,
        ]);

        return response()->json([
            'checkout_url' => $session->url,
            'session_id' => $session->id,
            'order_number' => $order->order_number,
        ]);
    }

    /**
     * Get order details
     * GET /api/orders/{orderNumber}
     */
    public function showOrder(string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(['event:id,name,slug,date,time,location', 'items'])
            ->firstOrFail();

        return response()->json([
            'order' => new OrderResource($order),
        ]);
    }
}
