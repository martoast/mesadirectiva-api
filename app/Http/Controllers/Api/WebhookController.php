<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrderConfirmation;
use App\Models\Event;
use App\Models\EventItem;
use App\Models\Order;
use App\Models\Seat;
use App\Models\Table;
use App\Models\TicketTier;
use App\Services\ReservationService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WebhookController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private ReservationService $reservationService
    ) {}

    /**
     * Handle Stripe webhooks
     * POST /api/webhooks/stripe
     */
    public function handleStripe(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = $this->stripeService->constructWebhookEvent($payload, $signature);
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return response('Invalid signature', 400);
        }

        Log::info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
            'charge.refunded' => $this->handleRefund($event->data->object),
            default => null,
        };

        return response('OK', 200);
    }

    private function handleCheckoutCompleted(object $session): void
    {
        $orderId = $session->metadata->order_id ?? null;

        if (!$orderId) {
            Log::warning('Checkout completed but no order_id in metadata', [
                'session_id' => $session->id,
            ]);
            return;
        }

        $order = Order::with('event')->find($orderId);

        if (!$order) {
            Log::warning('Order not found for checkout session', [
                'order_id' => $orderId,
            ]);
            return;
        }

        DB::transaction(function () use ($order, $session) {
            $order->markAsCompleted($session->payment_intent);

            $event = $order->event;

            if ($event->isSeated()) {
                // Complete reservation for seated events
                $reservationToken = $session->metadata->reservation_token ?? null;
                if ($reservationToken) {
                    $this->reservationService->completeReservation($reservationToken, $order->id);
                }

                // Mark tables and seats as sold based on order items
                $order->items()->where('item_type', 'ticket')->each(function ($item) {
                    if ($item->table_id) {
                        Table::where('id', $item->table_id)->update(['status' => 'sold']);
                    }
                    if ($item->seat_id) {
                        Seat::where('id', $item->seat_id)->update(['status' => 'sold']);
                    }
                });
            } else {
                // General admission - update ticket tier quantities
                $ticketItems = $order->items()->where('item_type', 'ticket')->get();
                foreach ($ticketItems as $ticketItem) {
                    if ($ticketItem->ticket_tier_id) {
                        TicketTier::where('id', $ticketItem->ticket_tier_id)
                            ->increment('quantity_sold', $ticketItem->quantity);
                    } else {
                        // Legacy support - increment event tickets_sold
                        Event::where('id', $order->event_id)
                            ->increment('tickets_sold', $ticketItem->quantity);
                    }
                }
            }

            // Update extra items quantity sold
            $extraItems = $order->items()->where('item_type', 'extra_item')->get();
            foreach ($extraItems as $orderItem) {
                EventItem::where('id', $orderItem->item_id)
                    ->increment('quantity_sold', $orderItem->quantity);
            }
        });

        // HERE IS THE EMAIL TO THE USER

        // Mail::to($order->customer_email)->send(new OrderConfirmation($order));

        Log::info('Order completed', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }

    private function handlePaymentFailed(object $paymentIntent): void
    {
        $orderId = $paymentIntent->metadata->order_id ?? null;

        if (!$orderId) {
            return;
        }

        $order = Order::find($orderId);
        $order?->markAsFailed();

        Log::info('Payment failed', [
            'order_id' => $orderId,
        ]);
    }

    private function handleRefund(object $charge): void
    {
        $order = Order::with('event')->where('stripe_payment_intent_id', $charge->payment_intent)->first();

        if (!$order || $order->status === 'refunded') {
            return;
        }

        DB::transaction(function () use ($order) {
            $order->markAsRefunded();

            $event = $order->event;

            if ($event->isSeated()) {
                // Release tables and seats back to available
                $order->items()->where('item_type', 'ticket')->each(function ($item) {
                    if ($item->table_id) {
                        Table::where('id', $item->table_id)->update(['status' => 'available']);
                    }
                    if ($item->seat_id) {
                        Seat::where('id', $item->seat_id)->update(['status' => 'available']);
                    }
                });
            } else {
                // General admission - decrement ticket tier quantities
                $ticketItems = $order->items()->where('item_type', 'ticket')->get();
                foreach ($ticketItems as $ticketItem) {
                    if ($ticketItem->ticket_tier_id) {
                        TicketTier::where('id', $ticketItem->ticket_tier_id)
                            ->decrement('quantity_sold', $ticketItem->quantity);
                    } else {
                        // Legacy support
                        Event::where('id', $order->event_id)
                            ->decrement('tickets_sold', $ticketItem->quantity);
                    }
                }
            }

            // Decrement extra items quantity sold
            $extraItems = $order->items()->where('item_type', 'extra_item')->get();
            foreach ($extraItems as $orderItem) {
                EventItem::where('id', $orderItem->item_id)
                    ->decrement('quantity_sold', $orderItem->quantity);
            }
        });

        Log::info('Order refunded', [
            'order_id' => $order->id,
        ]);
    }
}
