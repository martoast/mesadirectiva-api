<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Event;
use App\Models\EventItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seat;
use App\Models\Table;
use App\Models\TicketTier;
use App\Services\ReservationService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private ReservationService $reservationService
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

        if ($event->isSeated()) {
            return $this->createSeatedCheckout($request, $event);
        }

        return $this->createGeneralAdmissionCheckout($request, $event);
    }

    /**
     * Handle checkout for general admission events
     */
    private function createGeneralAdmissionCheckout(CreateCheckoutRequest $request, Event $event): JsonResponse
    {
        $tiers = $request->input('tiers', []);
        $legacyTickets = $request->input('tickets', 0);
        $extraItems = $request->input('extra_items', []);

        // Calculate subtotal from tiers
        $subtotal = 0;
        $lineItemsData = [];

        if (!empty($tiers)) {
            foreach ($tiers as $tierData) {
                $tier = TicketTier::where('event_id', $event->id)
                    ->where('id', $tierData['tier_id'])
                    ->first();

                if (!$tier || !$tier->isAvailable()) {
                    return response()->json([
                        'error' => "Ticket tier '{$tier?->name}' is not available",
                    ], 422);
                }

                $available = $tier->getAvailableQuantity();
                if ($available !== null && $tierData['quantity'] > $available) {
                    return response()->json([
                        'error' => "Not enough '{$tier->name}' tickets available",
                        'available' => $available,
                    ], 422);
                }

                $price = $tier->getCurrentPrice();
                $subtotal += $price * $tierData['quantity'];
                $lineItemsData[] = [
                    'type' => 'tier',
                    'tier' => $tier,
                    'quantity' => $tierData['quantity'],
                    'price' => $price,
                ];
            }
        } elseif ($legacyTickets > 0) {
            // Legacy support: use event price directly
            if ($legacyTickets > $event->getTicketsAvailable()) {
                return response()->json([
                    'error' => 'Not enough tickets available',
                    'available' => $event->getTicketsAvailable(),
                ], 422);
            }
            $subtotal += $event->price * $legacyTickets;
            $lineItemsData[] = [
                'type' => 'legacy_ticket',
                'quantity' => $legacyTickets,
                'price' => $event->price,
            ];
        }

        // Validate extra items
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
            $subtotal += $item->price * $extraItem['quantity'];
        }

        $total = $subtotal;

        // Create order
        $order = DB::transaction(function () use ($event, $request, $lineItemsData, $extraItems, $subtotal, $total, $legacyTickets) {
            $order = Order::create([
                'event_id' => $event->id,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'total' => $total,
            ]);

            foreach ($lineItemsData as $lineItem) {
                if ($lineItem['type'] === 'tier') {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_type' => 'ticket',
                        'ticket_tier_id' => $lineItem['tier']->id,
                        'item_name' => $event->name . ' - ' . $lineItem['tier']->name,
                        'quantity' => $lineItem['quantity'],
                        'unit_price' => $lineItem['price'],
                        'total_price' => $lineItem['price'] * $lineItem['quantity'],
                    ]);
                } elseif ($lineItem['type'] === 'legacy_ticket') {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_type' => 'ticket',
                        'item_name' => $event->name . ' - Ticket',
                        'quantity' => $lineItem['quantity'],
                        'unit_price' => $lineItem['price'],
                        'total_price' => $lineItem['price'] * $lineItem['quantity'],
                    ]);
                }
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

        // Build line items for Stripe
        $stripeLineItems = $this->buildGeneralAdmissionLineItems($event, $lineItemsData, $extraItems);

        return $this->finalizeCheckout($event, $order, $stripeLineItems);
    }

    /**
     * Handle checkout for seated events
     */
    private function createSeatedCheckout(CreateCheckoutRequest $request, Event $event): JsonResponse
    {
        $tableIds = $request->input('tables', []);
        $seatIds = $request->input('seats', []);
        $reservationToken = $request->input('reservation_token');
        $extraItems = $request->input('extra_items', []);

        // Validate reservation
        if (!$this->reservationService->validateReservation($reservationToken, $tableIds, $seatIds)) {
            return response()->json([
                'error' => 'Invalid or expired reservation',
            ], 422);
        }

        // Get tables and seats
        $tables = Table::whereIn('id', $tableIds)->get();
        $seats = Seat::whereIn('id', $seatIds)->get();

        // Calculate subtotal
        $subtotal = $tables->sum('price') + $seats->sum('price');

        // Validate extra items
        foreach ($extraItems as $extraItem) {
            $item = EventItem::find($extraItem['item_id']);
            if (!$item || !$item->isAvailable()) {
                return response()->json([
                    'error' => "Item '{$item?->name}' is not available",
                ], 422);
            }
            $subtotal += $item->price * $extraItem['quantity'];
        }

        $total = $subtotal;

        // Create order
        $order = DB::transaction(function () use ($event, $request, $tables, $seats, $extraItems, $subtotal, $total) {
            $order = Order::create([
                'event_id' => $event->id,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'total' => $total,
            ]);

            // Add table order items
            foreach ($tables as $table) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'item_type' => 'ticket',
                    'table_id' => $table->id,
                    'item_name' => $event->name . ' - ' . $table->name . ' (' . $table->capacity . ' seats)',
                    'quantity' => 1,
                    'unit_price' => $table->price,
                    'total_price' => $table->price,
                ]);
            }

            // Add seat order items
            foreach ($seats as $seat) {
                $table = $seat->table;
                OrderItem::create([
                    'order_id' => $order->id,
                    'item_type' => 'ticket',
                    'seat_id' => $seat->id,
                    'item_name' => $event->name . ' - ' . $table->name . ' - ' . $seat->label,
                    'quantity' => 1,
                    'unit_price' => $seat->price,
                    'total_price' => $seat->price,
                ]);
            }

            // Add extra items
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

        // Build line items for Stripe
        $stripeLineItems = $this->buildSeatedLineItems($event, $tables, $seats, $extraItems);

        return $this->finalizeCheckout($event, $order, $stripeLineItems, $reservationToken);
    }

    /**
     * Build Stripe line items for general admission
     */
    private function buildGeneralAdmissionLineItems(Event $event, array $lineItemsData, array $extraItems): array
    {
        $lineItems = [];

        foreach ($lineItemsData as $lineItem) {
            if ($lineItem['type'] === 'tier') {
                $tier = $lineItem['tier'];
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'mxn',
                        'product_data' => [
                            'name' => $event->name . ' - ' . $tier->name,
                            'description' => $tier->description ?? ($tier->isEarlyBird() ? 'Early Bird Price' : null),
                        ],
                        'unit_amount' => (int) ($lineItem['price'] * 100),
                    ],
                    'quantity' => $lineItem['quantity'],
                ];
            } elseif ($lineItem['type'] === 'legacy_ticket') {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'mxn',
                        'product_data' => [
                            'name' => $event->name . ' - Ticket',
                        ],
                        'unit_amount' => (int) ($lineItem['price'] * 100),
                    ],
                    'quantity' => $lineItem['quantity'],
                ];
            }
        }

        foreach ($extraItems as $extraItem) {
            $item = EventItem::find($extraItem['item_id']);
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'mxn',
                    'product_data' => [
                        'name' => $item->name,
                        'description' => $item->description,
                    ],
                    'unit_amount' => (int) ($item->price * 100),
                ],
                'quantity' => $extraItem['quantity'],
            ];
        }

        return $lineItems;
    }

    /**
     * Build Stripe line items for seated events
     */
    private function buildSeatedLineItems(Event $event, $tables, $seats, array $extraItems): array
    {
        $lineItems = [];

        foreach ($tables as $table) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'mxn',
                    'product_data' => [
                        'name' => $event->name . ' - ' . $table->name,
                        'description' => $table->capacity . ' seats',
                    ],
                    'unit_amount' => (int) ($table->price * 100),
                ],
                'quantity' => 1,
            ];
        }

        foreach ($seats as $seat) {
            $table = $seat->table;
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'mxn',
                    'product_data' => [
                        'name' => $event->name . ' - ' . $table->name . ' - ' . $seat->label,
                    ],
                    'unit_amount' => (int) ($seat->price * 100),
                ],
                'quantity' => 1,
            ];
        }

        foreach ($extraItems as $extraItem) {
            $item = EventItem::find($extraItem['item_id']);
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'mxn',
                    'product_data' => [
                        'name' => $item->name,
                        'description' => $item->description,
                    ],
                    'unit_amount' => (int) ($item->price * 100),
                ],
                'quantity' => $extraItem['quantity'],
            ];
        }

        return $lineItems;
    }

    /**
     * Finalize checkout by creating Stripe session
     */
    private function finalizeCheckout(Event $event, Order $order, array $lineItems, ?string $reservationToken = null): JsonResponse
    {
        $successUrl = config('app.frontend_url') . "/app/events/{$event->slug}/checkout-success";
        $cancelUrl = config('app.frontend_url') . "/app/events/{$event->slug}";

        $metadata = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ];

        if ($reservationToken) {
            $metadata['reservation_token'] = $reservationToken;
        }

        $session = $this->stripeService->createCheckoutSessionWithLineItems(
            $order,
            $lineItems,
            $successUrl,
            $cancelUrl,
            $metadata
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
