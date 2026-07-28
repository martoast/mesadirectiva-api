<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventItem;
use App\Models\Order;
use InvalidArgumentException;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

class StripeService
{
    private string $account;

    private ?StripeClient $client = null;

    public function __construct(?string $account = null)
    {
        $this->account = $account ?? config('services.stripe.default_account', 'cafeteria');

        if (!config("services.stripe.accounts.{$this->account}")) {
            throw new InvalidArgumentException("Unknown Stripe account [{$this->account}]");
        }
    }

    /**
     * Get a service instance bound to a specific Stripe account.
     */
    public function forAccount(?string $account): self
    {
        $account = $account ?: config('services.stripe.default_account', 'cafeteria');

        if ($account === $this->account) {
            return $this;
        }

        return new self($account);
    }

    /**
     * Get a service instance for an event's configured account.
     */
    public function forEvent(Event $event): self
    {
        return $this->forAccount($event->stripe_account);
    }

    public function account(): string
    {
        return $this->account;
    }

    /**
     * Lazily build the client so unconfigured accounts (e.g. rifa/eventos
     * before their keys exist) only fail when actually used.
     */
    private function client(): StripeClient
    {
        if ($this->client === null) {
            $secret = config("services.stripe.accounts.{$this->account}.secret");

            if (!$secret) {
                throw new InvalidArgumentException(
                    "Stripe account [{$this->account}] has no secret key configured"
                );
            }

            $this->client = new StripeClient($secret);
        }

        return $this->client;
    }

    /**
     * Create Stripe Product and Price for an event
     */
    public function createEventProduct(Event $event): array
    {
        $productData = [
            'name' => $event->name,
            'metadata' => [
                'event_id' => $event->id,
                'event_slug' => $event->slug,
            ],
        ];

        $description = trim(strip_tags($event->description ?? ''));
        if ($description !== '') {
            $productData['description'] = $description;
        }

        $product = $this->client()->products->create($productData);

        // Use first active tier's price, or 0 if no tiers
        $tier = $event->activeTicketTiers()->first();
        $unitAmount = $tier ? (int) ($tier->price * 100) : 0;

        $price = $this->client()->prices->create([
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
        $price = $this->client()->prices->create([
            'product' => $eventProductId,
            'unit_amount' => (int) ($item->price * 100),
            'currency' => 'mxn',
            'nickname' => $item->name,
            'metadata' => [
                'item_id' => $item->id,
                'item_type' => 'extra_item',
            ],
        ]);

        return $price->id;
    }

    /**
     * Create Checkout Session with pre-built line items
     */
    public function createCheckoutSessionWithLineItems(
        Order $order,
        array $lineItems,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): Session {
        $separator = str_contains($successUrl, '?') ? '&' : '?';

        return $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => $successUrl . $separator . 'session_id={CHECKOUT_SESSION_ID}',
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
     * Verify webhook signature against this account's signing secret
     */
    public function constructWebhookEvent(string $payload, string $signature): \Stripe\Event
    {
        $secret = config("services.stripe.accounts.{$this->account}.webhook_secret");

        if (!$secret) {
            throw new InvalidArgumentException(
                "Stripe account [{$this->account}] has no webhook secret configured"
            );
        }

        return \Stripe\Webhook::constructEvent($payload, $signature, $secret);
    }
}
