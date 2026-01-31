<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Str;

class TicketService
{
    /**
     * Generate a PDF ticket for a single order item
     */
    public function generateTicketPdf(OrderItem $orderItem): string
    {
        // Ensure ticket code exists
        if (!$orderItem->ticket_code) {
            $orderItem->generateTicketCode();
            $orderItem->refresh();
        }

        // Load relationships
        $orderItem->load(['order.event', 'ticketTier', 'table', 'seat']);

        // Generate QR code as base64 image
        $qrCode = $this->generateQrCode($orderItem->ticket_code);

        // Get custom ticket footer from event settings
        $event = $orderItem->order->event;
        $ticketFooter = $event->getEmailSetting('ticket_footer', 'Presenta este boleto en la entrada');

        // Generate PDF
        $pdf = Pdf::loadView('pdf.ticket', [
            'orderItem' => $orderItem,
            'order' => $orderItem->order,
            'event' => $event,
            'qrCode' => $qrCode,
            'ticketFooter' => $ticketFooter,
        ]);

        // Set paper size to a nice ticket size (4x6 inches)
        $pdf->setPaper([0, 0, 288, 432], 'portrait');

        return $pdf->output();
    }

    /**
     * Generate all ticket PDFs for an order
     * Returns array of [filename => pdf_content]
     */
    public function generateAllTicketsForOrder(Order $order): array
    {
        $tickets = [];

        // Load order with items and event
        $order->load(['event', 'items.ticketTier', 'items.table', 'items.seat']);

        // Only generate tickets for ticket items (not extra items)
        $ticketItems = $order->items->where('item_type', 'ticket');

        foreach ($ticketItems as $orderItem) {
            // Generate ticket code if not exists
            if (!$orderItem->ticket_code) {
                $orderItem->generateTicketCode();
                $orderItem->refresh();
            }

            // Generate PDF
            $pdfContent = $this->generateTicketPdf($orderItem);

            // Create filename from attendee name
            $attendeeName = $orderItem->attendee_name ?: 'ticket';
            $filename = 'ticket-' . Str::slug($attendeeName) . '.pdf';

            // Handle duplicate filenames
            $counter = 1;
            $originalFilename = $filename;
            while (isset($tickets[$filename])) {
                $filename = Str::replaceLast('.pdf', "-{$counter}.pdf", $originalFilename);
                $counter++;
            }

            $tickets[$filename] = $pdfContent;
        }

        return $tickets;
    }

    /**
     * Generate QR code as base64 data URI
     */
    private function generateQrCode(string $data): string
    {
        $builder = new Builder(
            writer: new PngWriter(),
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 200,
            margin: 10,
        );

        $result = $builder->build();

        return $result->getDataUri();
    }

    /**
     * Get the ticket type display name
     */
    public function getTicketTypeName(OrderItem $orderItem): string
    {
        // For General Admission - show tier name
        if ($orderItem->ticketTier) {
            return $orderItem->ticketTier->name;
        }

        // For Seated events - show table/seat info
        if ($orderItem->table && $orderItem->seat) {
            return $orderItem->table->name . ' · Seat ' . $orderItem->seat->label;
        }

        if ($orderItem->table) {
            return $orderItem->table->name;
        }

        // Fallback
        return $orderItem->item_name ?: 'General Admission';
    }
}
