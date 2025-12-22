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
            'category_id',
            'date_from',
            'date_to',
            'search',
        ]);

        $orders = $this->reportService->getSalesReport($request->user(), $filters);

        return response()->json([
            'orders' => OrderResource::collection($orders),
            'summary' => [
                'total_orders' => $orders->count(),
                'total_revenue' => $orders->sum('total'),
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
            'category_id',
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
            'category_id',
            'date_from',
            'date_to',
            'status',
            'search',
        ]);

        $orders = $this->reportService->getSalesReport($request->user(), $filters);

        return response()->json([
            'orders' => OrderResource::collection($orders),
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
            'category_id',
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
