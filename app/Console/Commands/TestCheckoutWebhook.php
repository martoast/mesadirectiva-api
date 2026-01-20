<?php

namespace App\Console\Commands;

use App\Mail\OrderTickets;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketTier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TestCheckoutWebhook extends Command
{
    protected $signature = 'test:checkout-webhook
                            {--order-id= : Test with existing order ID}
                            {--event-id= : Create test order for this event}
                            {--email=test@example.com : Customer email}
                            {--skip-email : Skip sending email}';

    protected $description = 'Test the checkout webhook flow end-to-end';

    public function handle(): int
    {
        $this->info('=== Testing Checkout Webhook Flow ===');
        $this->newLine();

        // Step 1: Get or create order
        $order = $this->getOrCreateOrder();
        if (!$order) {
            return 1;
        }

        $this->info("Order: {$order->order_number} (ID: {$order->id})");
        $this->info("Status: {$order->status}");
        $this->info("Customer: {$order->customer_email}");
        $this->info("Event: {$order->event->name}");
        $this->newLine();

        // Step 2: Simulate webhook processing
        $this->info('Step 2: Simulating checkout.session.completed webhook...');

        try {
            DB::transaction(function () use ($order) {
                $order->markAsCompleted('pi_test_' . uniqid());

                $event = $order->event;

                if (!$event->isSeated()) {
                    $ticketItems = $order->items()->where('item_type', 'ticket')->get();
                    foreach ($ticketItems as $ticketItem) {
                        if ($ticketItem->ticket_tier_id) {
                            TicketTier::where('id', $ticketItem->ticket_tier_id)
                                ->increment('quantity_sold', $ticketItem->quantity);
                            $this->info("  - Incremented ticket tier {$ticketItem->ticket_tier_id} by {$ticketItem->quantity}");
                        }
                    }
                }
            });

            $this->info('  ✓ Order marked as completed');
            $this->info("  ✓ New status: {$order->fresh()->status}");

        } catch (\Exception $e) {
            $this->error("  ✗ Failed to process order: {$e->getMessage()}");
            Log::error('Test webhook order processing failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }

        // Step 3: Test email sending
        if ($this->option('skip-email')) {
            $this->warn('Step 3: Skipping email (--skip-email flag)');
        } else {
            $this->info('Step 3: Testing email with PDF attachment...');

            try {
                $order->refresh();
                $order->load(['event', 'items.ticketTier', 'items.table', 'items.seat']);

                $this->info("  - Generating PDF tickets...");

                $mailable = new OrderTickets($order);

                // Test rendering the email
                $rendered = $mailable->render();
                $this->info("  ✓ Email rendered successfully (" . strlen($rendered) . " bytes)");

                // Actually send the email
                Mail::to($order->customer_email)->send($mailable);
                $this->info("  ✓ Email sent to {$order->customer_email}");

            } catch (\Exception $e) {
                $this->error("  ✗ Email failed: {$e->getMessage()}");
                Log::error('Test webhook email failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $this->newLine();
                $this->error('Stack trace:');
                $this->line($e->getTraceAsString());
                return 1;
            }
        }

        $this->newLine();
        $this->info('=== Test Complete ===');
        $this->info("Check logs: storage/logs/laravel.log");

        return 0;
    }

    private function getOrCreateOrder(): ?Order
    {
        if ($orderId = $this->option('order-id')) {
            $this->info("Step 1: Loading existing order #{$orderId}...");
            $order = Order::with('event')->find($orderId);

            if (!$order) {
                $this->error("Order #{$orderId} not found");
                return null;
            }

            return $order;
        }

        // Create a test order
        $eventId = $this->option('event-id');

        if (!$eventId) {
            $event = Event::first();
            if (!$event) {
                $this->error('No events found. Create an event first.');
                return null;
            }
            $eventId = $event->id;
        }

        $event = Event::with('ticketTiers')->find($eventId);
        if (!$event) {
            $this->error("Event #{$eventId} not found");
            return null;
        }

        $this->info("Step 1: Creating test order for event: {$event->name}");

        $ticketTier = $event->ticketTiers->first();
        if (!$ticketTier) {
            $this->error("Event has no ticket tiers");
            return null;
        }

        $order = Order::create([
            'order_number' => 'TEST-' . strtoupper(uniqid()),
            'event_id' => $event->id,
            'customer_name' => 'Test Customer',
            'customer_email' => $this->option('email'),
            'customer_phone' => '+1234567890',
            'status' => 'pending',
            'subtotal' => $ticketTier->price,
            'total' => $ticketTier->price,
            'stripe_checkout_session_id' => 'cs_test_' . uniqid(),
        ]);

        $order->items()->create([
            'item_type' => 'ticket',
            'ticket_tier_id' => $ticketTier->id,
            'item_name' => $ticketTier->name,
            'item_description' => $ticketTier->description,
            'quantity' => 1,
            'unit_price' => $ticketTier->price,
            'total_price' => $ticketTier->price,
            'attendee_name' => 'Test Attendee',
        ]);

        $order->load('event');
        $this->info("  ✓ Created order: {$order->order_number}");

        return $order;
    }
}
