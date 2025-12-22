<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order
    ) {
        $this->order->load('event', 'items');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order Confirmed - {$this->order->event->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.confirmation',
            with: [
                'order' => $this->order,
                'event' => $this->order->event,
                'ticketCount' => $this->order->getTicketCount(),
            ],
        );
    }
}
