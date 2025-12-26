<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventItemResource;
use App\Http\Resources\TicketTierResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicEventController extends Controller
{
    /**
     * List all live public events
     * GET /api/public/events
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::live()
            ->public()
            ->with('group')
            ->orderBy('starts_at', 'asc');

        if ($request->has('group')) {
            $query->whereHas('group', function ($q) use ($request) {
                $q->where('slug', $request->group);
            });
        }

        $events = $query->paginate($request->get('per_page', 12));

        return response()->json([
            'events' => EventResource::collection($events),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * Get single event details
     * GET /api/public/events/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)
            ->live()
            ->with(['group', 'activeItems', 'availableTicketTiers'])
            ->firstOrFail();

        return response()->json([
            'event' => new EventResource($event),
        ]);
    }

    /**
     * Check ticket/item availability
     * GET /api/public/events/{slug}/availability
     */
    public function availability(string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)
            ->live()
            ->with(['activeItems', 'activeTicketTiers', 'activeTables'])
            ->firstOrFail();

        $response = [
            'can_purchase' => $event->canPurchase(),
            'blocked_reason' => $event->getPurchaseBlockedReason(),
            'seating_type' => $event->seating_type ?? 'general_admission',
            'starts_at' => $event->starts_at,
            'ends_at' => $event->ends_at,
            'timezone' => $event->timezone,
        ];

        if ($event->isSeated()) {
            // Seated event - show tables and seats availability
            $tables = $event->activeTables;
            $tablesAvailable = $tables->where('status', 'available')->count();
            $seatsAvailable = 0;

            foreach ($tables->where('sell_as_whole', false) as $table) {
                $seatsAvailable += $table->activeSeats()->where('status', 'available')->count();
            }

            $response['tables_available'] = $tablesAvailable;
            $response['tables_total'] = $tables->count();
            $response['seats_available'] = $seatsAvailable;
            $response['seats_total'] = $tables->where('sell_as_whole', false)->sum(function ($table) {
                return $table->activeSeats()->count();
            });
        } else {
            // General admission - show tiers
            $response['tickets_available'] = $event->getTotalTicketsAvailable();
            $response['tickets_sold'] = $event->getTotalTicketsSold();
            $response['tiers'] = TicketTierResource::collection($event->activeTicketTiers);
        }

        // Extra items are available for both event types
        $response['items'] = $event->activeItems->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'price' => (float) $item->price,
                'available' => $item->isAvailable(),
                'available_quantity' => $item->getAvailableQuantity(),
            ];
        });

        return response()->json($response);
    }
}
