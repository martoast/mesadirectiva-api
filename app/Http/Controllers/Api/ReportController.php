<?php

namespace App\Http\Controllers\Api;

use App\Exports\OrdersExport;
use App\Exports\SalesExport;
use App\Exports\SummaryReportExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService
    ) {}

    /**
     * Get sales report
     * GET /api/reports/sales
     */
    public function sales(Request $request): JsonResponse
    {
        $filters = $request->only([
            'event_id',
            'group_id',
            'date_from',
            'date_to',
            'search',
        ]);

        if ($request->user()->isViewer()) {
            return $this->summaryResponse($request, $filters);
        }

        $perPage = (int) $request->input('per_page', 25);
        $paginator = $this->reportService->getSalesReportPaginated($request->user(), $filters, $perPage);
        $summary = $this->reportService->getSalesSummary($request->user(), $filters);

        return response()->json([
            'report_level' => 'full',
            'orders' => OrderResource::collection($paginator),
            'summary' => $summary,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Reduced payload for coordinadoras (role: viewer) — columns are
     * configured in config/reports.php.
     */
    private function summaryResponse(Request $request, array $filters): JsonResponse
    {
        $rows = $this->reportService->getSummaryReport($request->user(), $filters);
        $columns = config('reports.summary_columns');

        return response()->json([
            'report_level' => 'summary',
            'columns' => collect($columns)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
            ])->values(),
            'rows' => $rows,
            'summary' => [
                'total_rows' => $rows->count(),
                'total_amount' => round($rows->sum('total'), 2),
            ],
        ]);
    }

    /**
     * Export sales to Excel
     * GET /api/reports/sales/export
     */
    public function exportSales(Request $request): BinaryFileResponse
    {
        $filters = $request->only([
            'event_id',
            'group_id',
            'date_from',
            'date_to',
            'search',
        ]);

        if ($request->user()->isViewer()) {
            return Excel::download(
                new SummaryReportExport($request->user(), $filters),
                'reporte-resumido-' . now()->format('Y-m-d') . '.xlsx'
            );
        }

        $filename = 'sales-report-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new SalesExport($request->user(), $filters),
            $filename
        );
    }

    /**
     * Get orders report
     * GET /api/reports/orders
     */
    public function orders(Request $request): JsonResponse
    {
        $filters = $request->only([
            'event_id',
            'group_id',
            'date_from',
            'date_to',
            'status',
            'search',
        ]);

        if ($request->user()->isViewer()) {
            return $this->summaryResponse($request, $filters);
        }

        $perPage = (int) $request->input('per_page', 25);
        $paginator = $this->reportService->getSalesReportPaginated($request->user(), $filters, $perPage);

        return response()->json([
            'report_level' => 'full',
            'orders' => OrderResource::collection($paginator),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Export orders to Excel
     * GET /api/reports/orders/export
     */
    public function exportOrders(Request $request): BinaryFileResponse
    {
        $filters = $request->only([
            'event_id',
            'group_id',
            'date_from',
            'date_to',
            'status',
            'search',
        ]);

        if ($request->user()->isViewer()) {
            return Excel::download(
                new SummaryReportExport($request->user(), $filters),
                'reporte-resumido-' . now()->format('Y-m-d') . '.xlsx'
            );
        }

        $filename = 'orders-report-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new OrdersExport($request->user(), $filters),
            $filename
        );
    }
}
