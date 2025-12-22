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
            'description' => $event->about,
            'metadata' => [
                'event_id' => $event->id,
                'event_slug' => $event->slug,
            ],
        ]);

        $price = Price::create([
            'product' => $product->id,
            'unit_amount' => (int) ($event->price * 100),
            'currency' => 'usd',
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

        // Add tickets
        if ($ticketQuantity > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => (int) ($event->price * 100), // Convert to cents
                    'product_data' => [
                        'name' => $event->name . ' - Ticket',
                        'description' => "Ticket for {$event->name} on {$event->date->format('F j, Y')}",
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
                        'currency' => 'usd',
                        'unit_amount' => (int) ($item->price * 100), // Convert to cents
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
