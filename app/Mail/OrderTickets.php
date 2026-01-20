<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\TicketService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrderTickets extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;
    private array $tickets = [];

    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->order->load(['event', 'items.ticketTier', 'items.table', 'items.seat']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tus boletos para {$this->order->event->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-tickets',
            with: [
                'order' => $this->order,
                'event' => $this->order->event,
                'ticketItems' => $this->order->items->where('item_type', 'ticket'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        Log::info('Generating ticket attachments', [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
        ]);

        try {
            $ticketService = app(TicketService::class);
            $tickets = $ticketService->generateAllTicketsForOrder($this->order);

            Log::info('Tickets generated', [
                'order_id' => $this->order->id,
                'ticket_count' => count($tickets),
                'filenames' => array_keys($tickets),
            ]);

            $attachments = [];
            foreach ($tickets as $filename => $pdfContent) {
                $attachments[] = Attachment::fromData(fn() => $pdfContent, $filename)
                    ->withMime('application/pdf');
            }

            return $attachments;
        } catch (\Exception $e) {
            Log::error('Failed to generate ticket attachments', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
