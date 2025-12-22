<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventItemResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicEventController extends Controller
{
    /**
     * List all live events
     * GET /api/public/events
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::live()
            ->with('category')
            ->orderBy('date', 'asc');

        if ($request->has('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
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
            ->with(['category', 'activeItems'])
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
            ->with('activeItems')
            ->firstOrFail();

        return response()->json([
            'can_purchase' => $event->canPurchase(),
            'blocked_reason' => $event->getPurchaseBlockedReason(),
            'tickets_available' => $event->getTicketsAvailable(),
            'registration_open' => $event->registration_open,
            'registration_deadline' => $event->registration_deadline,
            'items' => $event->activeItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => (float) $item->price,
                    'available' => $item->isAvailable(),
                    'available_quantity' => $item->getAvailableQuantity(),
                ];
            }),
        ]);
    }
}
