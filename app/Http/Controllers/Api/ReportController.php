<?php

namespace App\Http\Controllers\Api;

use App\Exports\OrdersExport;
use App\Exports\SalesExport;
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

        $perPage = (int) $request->input('per_page', 25);
        $paginator = $this->reportService->getSalesReportPaginated($request->user(), $filters, $perPage);
        $summary = $this->reportService->getSalesSummary($request->user(), $filters);

        return response()->json([
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

        $perPage = (int) $request->input('per_page', 25);
        $paginator = $this->reportService->getSalesReportPaginated($request->user(), $filters, $perPage);

        return response()->json([
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

        $filename = 'orders-report-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new OrdersExport($request->user(), $filters),
            $filename
        );
    }
}
