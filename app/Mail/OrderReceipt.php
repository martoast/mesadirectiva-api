<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Purchase receipt for products (kind = product). Unlike OrderTickets there
 * are no PDF/QR attachments — just what was bought and where to pick it up.
 */
class OrderReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->order->load(['event', 'items.ticketTier']);
    }

    public function envelope(): Envelope
    {
        $event = $this->order->event;

        return new Envelope(
            subject: $event->getEmailSetting(
                'email_subject',
                'Comprobante de compra: {event_name}',
                $this->replacements()
            ),
        );
    }

    public function content(): Content
    {
        $event = $this->order->event;
        $replacements = $this->replacements();

        return new Content(
            view: 'emails.order-receipt',
            with: [
                'order' => $this->order,
                'event' => $event,
                'lineItems' => $this->order->items,
                'emailGreeting' => $event->getEmailSetting('email_greeting', 'Hola {customer_name},', $replacements),
                'emailIntro' => $event->getEmailSetting(
                    'email_intro',
                    'Recibimos tu pago. Este es el comprobante de tu compra.',
                    $replacements
                ),
                'emailInstructions' => $event->getEmailSetting(
                    'email_instructions',
                    $event->location['instructions'] ?? null,
                    $replacements
                ),
                'emailFooter' => $event->getEmailSetting('email_footer', '¡Gracias por tu compra!', $replacements),
                'pickupLocation' => $event->location['name'] ?? null,
            ],
        );
    }

    private function replacements(): array
    {
        return ['{customer_name}' => $this->order->customer_name];
    }
}
