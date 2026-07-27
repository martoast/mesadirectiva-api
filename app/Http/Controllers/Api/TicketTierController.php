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
            'tiers' => TicketTierResource::collection($event->ticketTiers()->with('dependsOn')->ordered()->get()),
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

        $data = $request->validated();

        if ($error = $this->validateDependency($event->id, null, $data['depends_on_tier_id'] ?? null)) {
            return $error;
        }

        $tier = TicketTier::create([
            'event_id' => $event->id,
            ...$data,
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

        $data = $request->validated();

        if (array_key_exists('depends_on_tier_id', $data)
            && ($error = $this->validateDependency($event->id, $tier->id, $data['depends_on_tier_id']))) {
            return $error;
        }

        $tier->update($data);

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

    /**
     * Validate an installment dependency: same event, no self-reference,
     * no cycles, and chains capped at 3 payments total.
     * Returns an error response or null when valid.
     */
    private function validateDependency(int $eventId, ?int $tierId, ?int $dependsOnId): ?JsonResponse
    {
        if (!$dependsOnId) {
            return null;
        }

        if ($tierId && $dependsOnId === $tierId) {
            return response()->json([
                'message' => 'A payment cannot depend on itself',
            ], 422);
        }

        $parent = TicketTier::where('event_id', $eventId)->find($dependsOnId);

        if (!$parent) {
            return response()->json([
                'message' => 'The selected payment must belong to the same event',
            ], 422);
        }

        // Walk up from the proposed parent: detect cycles and measure depth
        $depth = 1;
        $current = $parent;
        while ($current->depends_on_tier_id !== null) {
            if ($tierId && $current->depends_on_tier_id === $tierId) {
                return response()->json([
                    'message' => 'Payments cannot depend on each other in a circle',
                ], 422);
            }
            $current = $current->dependsOn;
            $depth++;
            if ($depth >= 3) {
                return response()->json([
                    'message' => 'Payment chains are limited to 3 payments',
                ], 422);
            }
        }

        return null;
    }

    public function reorder(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $request->validate([
            'tier_ids' => 'required|array',
            'tier_ids.*' => 'integer|exists:ticket_tiers,id',
        ]);

        $tierIds = $request->input('tier_ids');

        // Verify all tiers belong to this event
        $eventTierIds = $event->ticketTiers()->pluck('id')->toArray();
        foreach ($tierIds as $tierId) {
            if (!in_array($tierId, $eventTierIds)) {
                return response()->json([
                    'message' => 'One or more tier IDs do not belong to this event',
                ], 400);
            }
        }

        // Update sort_order for each tier
        foreach ($tierIds as $index => $tierId) {
            TicketTier::where('id', $tierId)->update(['sort_order' => $index]);
        }

        return response()->json([
            'message' => 'Ticket tiers reordered successfully',
            'tiers' => TicketTierResource::collection($event->ticketTiers()->ordered()->get()),
        ]);
    }
}
