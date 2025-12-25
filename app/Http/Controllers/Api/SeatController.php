<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSeatRequest;
use App\Http\Requests\UpdateSeatRequest;
use App\Http\Resources\SeatResource;
use App\Models\Event;
use App\Models\Seat;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeatController extends Controller
{
    public function index(Request $request, string $slug, int $tableId): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('view', $event);

        $table = Table::where('event_id', $event->id)
            ->where('id', $tableId)
            ->firstOrFail();

        if ($table->sell_as_whole) {
            return response()->json([
                'message' => 'This table is sold as a whole and does not have individual seats',
            ], 400);
        }

        return response()->json([
            'seats' => SeatResource::collection($table->seats),
        ]);
    }

    public function store(StoreSeatRequest $request, string $slug, int $tableId): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $table = Table::where('event_id', $event->id)
            ->where('id', $tableId)
            ->firstOrFail();

        if ($table->sell_as_whole) {
            return response()->json([
                'message' => 'Cannot add individual seats to a table sold as a whole',
            ], 400);
        }

        $seat = Seat::create([
            'table_id' => $table->id,
            ...$request->validated(),
        ]);

        return response()->json([
            'message' => 'Seat created successfully',
            'seat' => new SeatResource($seat),
        ], 201);
    }

    public function update(UpdateSeatRequest $request, string $slug, int $seatId): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $seat = Seat::whereHas('table', function ($query) use ($event) {
            $query->where('event_id', $event->id);
        })->where('id', $seatId)->firstOrFail();

        if ($seat->isSold()) {
            return response()->json([
                'message' => 'Cannot update a sold seat',
            ], 400);
        }

        $seat->update($request->validated());

        return response()->json([
            'message' => 'Seat updated successfully',
            'seat' => new SeatResource($seat),
        ]);
    }

    public function destroy(Request $request, string $slug, int $seatId): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $seat = Seat::whereHas('table', function ($query) use ($event) {
            $query->where('event_id', $event->id);
        })->where('id', $seatId)->firstOrFail();

        if ($seat->isSold()) {
            return response()->json([
                'message' => 'Cannot delete a sold seat',
            ], 400);
        }

        $seat->delete();

        return response()->json([
            'message' => 'Seat deleted successfully',
        ]);
    }

    public function bulkStore(Request $request, string $slug, int $tableId): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $table = Table::where('event_id', $event->id)
            ->where('id', $tableId)
            ->firstOrFail();

        if ($table->sell_as_whole) {
            return response()->json([
                'message' => 'Cannot add individual seats to a table sold as a whole',
            ], 400);
        }

        $request->validate([
            'seats' => 'required|array|min:1',
            'seats.*.label' => 'required|string|max:255',
            'seats.*.price' => 'required|numeric|min:0',
            'seats.*.position_x' => 'nullable|integer',
            'seats.*.position_y' => 'nullable|integer',
        ]);

        $seats = collect($request->input('seats'))->map(function ($seatData) use ($table) {
            return Seat::create([
                'table_id' => $table->id,
                ...$seatData,
            ]);
        });

        return response()->json([
            'message' => count($seats) . ' seats created successfully',
            'seats' => SeatResource::collection($seats),
        ], 201);
    }
}
