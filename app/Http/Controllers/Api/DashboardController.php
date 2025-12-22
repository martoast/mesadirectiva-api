<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private ReportService $reportService
    ) {}

    /**
     * Get overall dashboard statistics
     * GET /api/dashboard/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->reportService->getDashboardStats($request->user());

        return response()->json([
            'stats' => $stats,
        ]);
    }

    /**
     * Get event-specific statistics
     * GET /api/dashboard/events/{slug}/stats
     */
    public function eventStats(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('view', $event);

        $stats = $this->reportService->getEventStats($event);

        return response()->json($stats);
    }
}
