<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendeeResource;
use App\Models\Event;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendeeController extends Controller
{
    /**
     * List all attendees for an event with filters and search
     * GET /api/events/{slug}/attendees
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        // Authorization: user must be able to view the event
        $this->authorize('view', $event);

        $query = OrderItem::whereHas('order', function ($q) use ($event) {
            $q->where('event_id', $event->id)
              ->where('status', 'completed'); // Only show attendees from paid orders
        })
        ->where('item_type', 'ticket')
        ->with(['order', 'ticketTier', 'table', 'seat', 'checkedInBy']);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('attendee_name', 'like', "%{$search}%")
                  ->orWhere('student_key', 'like', "%{$search}%")
                  ->orWhereHas('order', function ($oq) use ($search) {
                      $oq->where('customer_name', 'like', "%{$search}%")
                         ->orWhere('customer_email', 'like', "%{$search}%")
                         ->orWhere('order_number', 'like', "%{$search}%");
                  });
            });
        }

        // Order status filter
        if ($request->filled('status')) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->where('status', $request->status);
            });
        }

        // Check-in status filter
        if ($request->filled('checked_in')) {
            if ($request->checked_in === 'checked_in') {
                $query->whereNotNull('checked_in_at');
            } elseif ($request->checked_in === 'not_checked_in') {
                $query->whereNull('checked_in_at');
            }
            // 'all' - no filter
        }

        // Type filter for seated events (tables, seats, all)
        if ($request->filled('type')) {
            if ($request->type === 'tables') {
                $query->whereNotNull('table_id');
            } elseif ($request->type === 'seats') {
                $query->whereNotNull('seat_id');
            }
        }

        // Get totals for stats (before pagination)
        $totalQuery = clone $query;
        $checkedInQuery = clone $query;
        $notCheckedInQuery = clone $query;

        $total = $totalQuery->count();
        $checkedInCount = $checkedInQuery->whereNotNull('checked_in_at')->count();
        $notCheckedInCount = $notCheckedInQuery->whereNull('checked_in_at')->count();

        // Order by attendee name, then by check-in status
        $query->orderByRaw('checked_in_at IS NULL DESC') // Not checked in first
              ->orderBy('attendee_name', 'asc');

        $perPage = $request->get('per_page', 50);
        $attendees = $query->paginate($perPage);

        return response()->json([
            'attendees' => AttendeeResource::collection($attendees),
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'seating_type' => $event->seating_type,
            ],
            'meta' => [
                'total' => $total,
                'checked_in_count' => $checkedInCount,
                'not_checked_in_count' => $notCheckedInCount,
                'current_page' => $attendees->currentPage(),
                'last_page' => $attendees->lastPage(),
                'per_page' => $attendees->perPage(),
            ],
        ]);
    }

    /**
     * Check in an attendee
     * POST /api/events/{slug}/attendees/{orderItemId}/check-in
     */
    public function checkIn(Request $request, string $slug, int $orderItemId): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        // Authorization: user must be able to update the event
        $this->authorize('update', $event);

        $orderItem = OrderItem::whereHas('order', function ($q) use ($event) {
            $q->where('event_id', $event->id)
              ->where('status', 'completed'); // Only allow check-in for paid orders
        })
        ->where('id', $orderItemId)
        ->where('item_type', 'ticket')
        ->firstOrFail();

        if ($orderItem->isCheckedIn()) {
            return response()->json([
                'message' => 'Attendee is already checked in',
            ], 422);
        }

        $orderItem->checkIn($request->user()->id);

        // Reload with relationships
        $orderItem->load(['order', 'ticketTier', 'table', 'seat', 'checkedInBy']);

        return response()->json([
            'message' => 'Attendee checked in successfully',
            'attendee' => new AttendeeResource($orderItem),
        ]);
    }

    /**
     * Check in an attendee by scanning their ticket QR code
     * POST /api/events/{slug}/attendees/scan
     */
    public function scanCheckIn(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'ticket_code' => 'required|string|max:20',
        ]);

        $event = Event::where('slug', $slug)->firstOrFail();

        // Authorization: user must be able to update the event
        $this->authorize('update', $event);

        // Find order item by ticket code
        $orderItem = OrderItem::findByTicketCode($request->ticket_code);

        if (!$orderItem) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid ticket code',
            ], 404);
        }

        // Verify ticket belongs to this event
        $orderItem->load(['order.event', 'ticketTier', 'table', 'seat', 'checkedInBy']);

        if ($orderItem->order->event_id !== $event->id) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket is for a different event',
            ], 422);
        }

        // Verify order is completed
        if ($orderItem->order->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'This ticket has not been paid for',
            ], 422);
        }

        // Check if already checked in
        if ($orderItem->isCheckedIn()) {
            return response()->json([
                'success' => false,
                'message' => 'Attendee is already checked in',
                'attendee' => new AttendeeResource($orderItem),
                'already_checked_in' => true,
            ], 422);
        }

        // Check them in
        $orderItem->checkIn($request->user()->id);

        // Reload with relationships
        $orderItem->load(['order', 'ticketTier', 'table', 'seat', 'checkedInBy']);

        return response()->json([
            'success' => true,
            'message' => 'Attendee checked in successfully',
            'attendee' => new AttendeeResource($orderItem),
        ]);
    }

    /**
     * Undo check-in for an attendee
     * POST /api/events/{slug}/attendees/{orderItemId}/undo-check-in
     */
    public function undoCheckIn(Request $request, string $slug, int $orderItemId): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        // Authorization: user must be able to update the event
        $this->authorize('update', $event);

        $orderItem = OrderItem::whereHas('order', function ($q) use ($event) {
            $q->where('event_id', $event->id)
              ->where('status', 'completed'); // Only allow for paid orders
        })
        ->where('id', $orderItemId)
        ->where('item_type', 'ticket')
        ->firstOrFail();

        if (!$orderItem->isCheckedIn()) {
            return response()->json([
                'message' => 'Attendee is not checked in',
            ], 422);
        }

        $orderItem->undoCheckIn();

        // Reload with relationships
        $orderItem->load(['order', 'ticketTier', 'table', 'seat', 'checkedInBy']);

        return response()->json([
            'message' => 'Check-in undone successfully',
            'attendee' => new AttendeeResource($orderItem),
        ]);
    }
}
