<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReserveRequest;
use App\Http\Resources\ReservationResource;
use App\Http\Resources\SeatResource;
use App\Http\Resources\TableResource;
use App\Http\Resources\TicketTierResource;
use App\Models\Event;
use App\Models\Table;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSeatingController extends Controller
{
    public function __construct(
        private ReservationService $reservationService
    ) {}

    /**
     * Get available ticket tiers for a general admission event
     */
    public function ticketTiers(string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)
            ->where('status', 'live')
            ->firstOrFail();

        if ($event->isSeated()) {
            return response()->json([
                'message' => 'This is a seated event. Use the tables endpoint instead.',
            ], 400);
        }

        $tiers = $event->activeTicketTiers;

        return response()->json([
            'tiers' => TicketTierResource::collection($tiers),
        ]);
    }

    /**
     * Get available tables for a seated event
     */
    public function tables(string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)
            ->where('status', 'live')
            ->firstOrFail();

        if (!$event->isSeated()) {
            return response()->json([
                'message' => 'This is a general admission event. Use the ticket-tiers endpoint instead.',
            ], 400);
        }

        $tables = $event->activeTables()
            ->with(['seats' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get();

        return response()->json([
            'tables' => TableResource::collection($tables),
        ]);
    }

    /**
     * Get available seats for a specific table
     */
    public function seats(string $slug, int $tableId): JsonResponse
    {
        $event = Event::where('slug', $slug)
            ->where('status', 'live')
            ->firstOrFail();

        if (!$event->isSeated()) {
            return response()->json([
                'message' => 'This is a general admission event.',
            ], 400);
        }

        $table = Table::where('event_id', $event->id)
            ->where('id', $tableId)
            ->where('is_active', true)
            ->firstOrFail();

        if ($table->sell_as_whole) {
            return response()->json([
                'message' => 'This table is sold as a whole. Individual seats are not available.',
            ], 400);
        }

        $seats = $table->activeSeats;

        return response()->json([
            'table' => new TableResource($table),
            'seats' => SeatResource::collection($seats),
        ]);
    }

    /**
     * Reserve tables and/or seats
     */
    public function reserve(ReserveRequest $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)
            ->where('status', 'live')
            ->firstOrFail();

        if (!$event->isSeated()) {
            return response()->json([
                'message' => 'Reservations are only available for seated events.',
            ], 400);
        }

        if (!$event->canPurchase()) {
            return response()->json([
                'message' => 'This event is not available for purchase.',
                'reason' => $event->getPurchaseBlockedReason(),
            ], 400);
        }

        try {
            $reservation = $this->reservationService->reserve(
                $event,
                $request->input('tables', []),
                $request->input('seats', [])
            );

            return response()->json([
                'message' => 'Reservation created successfully',
                'reservation' => new ReservationResource($reservation),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Release a reservation
     */
    public function releaseReservation(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $this->reservationService->release($request->input('token'));

        return response()->json([
            'message' => 'Reservation released successfully',
        ]);
    }
}
