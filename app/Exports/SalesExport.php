<?php

namespace App\Exports;

use App\Models\User;
use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private User $user,
        private array $filters = []
    ) {}

    public function collection()
    {
        return app(ReportService::class)->getSalesReport($this->user, $this->filters);
    }

    public function headings(): array
    {
        return [
            'Order Number',
            'Date',
            'Event',
            'Group',
            'Customer Name',
            'Customer Email',
            'Tickets',
            'Total',
            'Status',
            'Attendee Name(s)',
            'Attendee Note(s)',
        ];
    }

    public function map($order): array
    {
        $attendeeNames = $order->items->pluck('attendee_name')->filter()->join(', ');
        $attendeeNotes = $order->items->pluck('attendee_note')->filter()->join(', ');

        return [
            $order->order_number,
            $order->paid_at?->format('Y-m-d H:i'),
            $order->event->name,
            $order->event->group?->name ?? 'N/A',
            $order->customer_name,
            $order->customer_email,
            $order->getTicketCount(),
            '$' . number_format($order->total, 2),
            ucfirst($order->status),
            $attendeeNames ?: 'N/A',
            $attendeeNotes ?: 'N/A',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
