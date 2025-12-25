<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketTierRequest;
use App\Http\Requests\UpdateTicketTierRequest;
use App\Http\Resources\TicketTierResource;
use App\Models\Event;
use App\Models\TicketTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketTierController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('view', $event);

        if ($event->isSeated()) {
            return response()->json([
                'message' => 'Ticket tiers are not available for seated events',
            ], 400);
        }

        return response()->json([
            'tiers' => TicketTierResource::collection($event->ticketTiers()->ordered()->get()),
        ]);
    }

    public function store(StoreTicketTierRequest $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        if ($event->isSeated()) {
            return response()->json([
                'message' => 'Cannot add ticket tiers to a seated event',
            ], 400);
        }

        $tier = TicketTier::create([
            'event_id' => $event->id,
            ...$request->validated(),
            'quantity_sold' => 0,
        ]);

        return response()->json([
            'message' => 'Ticket tier created successfully',
            'tier' => new TicketTierResource($tier),
        ], 201);
    }

    public function show(Request $request, string $slug, int $tierId): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('view', $event);

        $tier = TicketTier::where('event_id', $event->id)
            ->where('id', $tierId)
            ->firstOrFail();

        return response()->json([
            'tier' => new TicketTierResource($tier),
        ]);
    }

    public function update(UpdateTicketTierRequest $request, string $slug, int $tierId): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $tier = TicketTier::where('event_id', $event->id)
            ->where('id', $tierId)
            ->firstOrFail();

        $tier->update($request->validated());

        return response()->json([
            'message' => 'Ticket tier updated successfully',
            'tier' => new TicketTierResource($tier),
        ]);
    }

    public function destroy(Request $request, string $slug, int $tierId): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $tier = TicketTier::where('event_id', $event->id)
            ->where('id', $tierId)
            ->firstOrFail();

        // Don't allow deletion if tickets have been sold
        if ($tier->quantity_sold > 0) {
            return response()->json([
                'message' => 'Cannot delete a tier that has sold tickets',
            ], 400);
        }

        $tier->delete();

        return response()->json([
            'message' => 'Ticket tier deleted successfully',
        ]);
    }
}
