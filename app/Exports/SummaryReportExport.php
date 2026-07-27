<?php

namespace App\Exports;

use App\Models\User;
use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reduced export for coordinadoras (role: viewer). Columns come from
 * config/reports.php so the team can adjust them without code changes.
 */
class SummaryReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private User $user,
        private array $filters = []
    ) {}

    public function collection()
    {
        return app(ReportService::class)->getSummaryReport($this->user, $this->filters);
    }

    public function headings(): array
    {
        return array_values(config('reports.summary_columns'));
    }

    public function map($row): array
    {
        return array_map(function ($key) use ($row) {
            $value = $row[$key] ?? '';

            return $key === 'total' ? '$' . number_format((float) $value, 2) : $value;
        }, array_keys(config('reports.summary_columns')));
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
