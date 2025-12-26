<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventItem;
use App\Models\Order;
use Stripe\Checkout\Session;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create Stripe Product and Price for an event
     */
    public function createEventProduct(Event $event): array
    {
        $product = Product::create([
            'name' => $event->name,
            'description' => strip_tags($event->description ?? ''),
            'metadata' => [
                'event_id' => $event->id,
                'event_slug' => $event->slug,
            ],
        ]);

        // Use first active tier's price, or 0 if no tiers
        $tier = $event->activeTicketTiers()->first();
        $unitAmount = $tier ? (int) ($tier->price * 100) : 0;

        $price = Price::create([
            'product' => $product->id,
            'unit_amount' => $unitAmount,
            'currency' => 'mxn',
        ]);

        return [
            'product_id' => $product->id,
            'price_id' => $price->id,
        ];
    }

    /**
     * Create Stripe Price for an event item
     */
    public function createItemPrice(EventItem $item, string $eventProductId): string
    {
        $price = Price::create([
            'product' => $eventProductId,
            'unit_amount' => (int) ($item->price * 100),
            'currency' => 'usd',
            'nickname' => $item->name,
            'metadata' => [
                'item_id' => $item->id,
                'item_type' => 'extra_item',
            ],
        ]);

        return $price->id;
    }

    /**
     * Create Checkout Session
     */
    public function createCheckoutSession(
        Event $event,
        Order $order,
        array $lineItems,
        string $successUrl,
        string $cancelUrl
    ): Session {
        return Session::create([
            'mode' => 'payment',
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'customer_email' => $order->customer_email,
            'line_items' => $lineItems,
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'event_id' => $event->id,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ],
            ],
        ]);
    }

    /**
     * Build line items array for checkout using dynamic price_data
     * This allows flexible pricing without pre-creating Stripe products
     */
    public function buildLineItems(Event $event, int $ticketQuantity, array $extraItems = []): array
    {
        $lineItems = [];

        // Add tickets using first available tier
        if ($ticketQuantity > 0) {
            $tier = $event->availableTicketTiers()->first();
            $price = $tier ? $tier->price : 0;
            $dateStr = $event->starts_at ? $event->starts_at->format('F j, Y') : '';

            $lineItems[] = [
                'price_data' => [
                    'currency' => 'mxn',
                    'unit_amount' => (int) ($price * 100),
                    'product_data' => [
                        'name' => $event->name . ' - ' . ($tier->name ?? 'Ticket'),
                        'description' => "Ticket for {$event->name}" . ($dateStr ? " on {$dateStr}" : ''),
                        'metadata' => [
                            'event_id' => $event->id,
                            'event_slug' => $event->slug,
                            'type' => 'ticket',
                        ],
                    ],
                ],
                'quantity' => $ticketQuantity,
            ];
        }

        // Add extra items
        foreach ($extraItems as $extraItem) {
            $item = EventItem::find($extraItem['item_id']);
            if ($item && $item->is_active) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'mxn',
                        'unit_amount' => (int) ($item->price * 100),
                        'product_data' => [
                            'name' => $item->name,
                            'description' => $item->description ?? "Add-on for {$event->name}",
                            'metadata' => [
                                'event_id' => $event->id,
                                'item_id' => $item->id,
                                'type' => 'extra_item',
                            ],
                        ],
                    ],
                    'quantity' => $extraItem['quantity'],
                ];
            }
        }

        return $lineItems;
    }

    /**
     * Create Checkout Session with pre-built line items
     * Used for tier-based and seated event checkouts
     */
    public function createCheckoutSessionWithLineItems(
        Order $order,
        array $lineItems,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): Session {
        return Session::create([
            'mode' => 'payment',
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'customer_email' => $order->customer_email,
            'line_items' => $lineItems,
            'metadata' => array_merge([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'event_id' => $order->event_id,
            ], $metadata),
            'payment_intent_data' => [
                'metadata' => array_merge([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ], $metadata),
            ],
        ]);
    }

    /**
     * Verify webhook signature
     */
    public function constructWebhookEvent(string $payload, string $signature): \Stripe\Event
    {
        return \Stripe\Webhook::constructEvent(
            $payload,
            $signature,
            config('services.stripe.webhook_secret')
        );
    }
}
