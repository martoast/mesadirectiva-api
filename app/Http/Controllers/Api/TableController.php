<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTableRequest;
use App\Http\Requests\UpdateTableRequest;
use App\Http\Resources\TableResource;
use App\Models\Event;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('view', $event);

        if (!$event->isSeated()) {
            return response()->json([
                'message' => 'Tables are only available for seated events',
            ], 400);
        }

        $tables = $event->tables()->with('seats')->get();

        return response()->json([
            'tables' => TableResource::collection($tables),
        ]);
    }

    public function store(StoreTableRequest $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        if (!$event->isSeated()) {
            return response()->json([
                'message' => 'Cannot add tables to a general admission event',
            ], 400);
        }

        $table = Table::create([
            'event_id' => $event->id,
            ...$request->validated(),
        ]);

        return response()->json([
            'message' => 'Table created successfully',
            'table' => new TableResource($table),
        ], 201);
    }

    public function show(Request $request, string $slug, int $tableId): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('view', $event);

        $table = Table::where('event_id', $event->id)
            ->where('id', $tableId)
            ->with('seats')
            ->firstOrFail();

        return response()->json([
            'table' => new TableResource($table),
        ]);
    }

    public function update(UpdateTableRequest $request, string $slug, int $tableId): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $table = Table::where('event_id', $event->id)
            ->where('id', $tableId)
            ->firstOrFail();

        // Don't allow changing sell_as_whole if table is sold
        if ($table->isSold() && $request->has('sell_as_whole')) {
            return response()->json([
                'message' => 'Cannot change sell mode for a sold table',
            ], 400);
        }

        $table->update($request->validated());

        return response()->json([
            'message' => 'Table updated successfully',
            'table' => new TableResource($table->load('seats')),
        ]);
    }

    public function destroy(Request $request, string $slug, int $tableId): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $table = Table::where('event_id', $event->id)
            ->where('id', $tableId)
            ->firstOrFail();

        if ($table->isSold()) {
            return response()->json([
                'message' => 'Cannot delete a sold table',
            ], 400);
        }

        $table->delete();

        return response()->json([
            'message' => 'Table deleted successfully',
        ]);
    }

    public function bulkStore(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        if (!$event->isSeated()) {
            return response()->json([
                'message' => 'Cannot add tables to a general admission event',
            ], 400);
        }

        $request->validate([
            'tables' => 'required|array|min:1',
            'tables.*.name' => 'required|string|max:255',
            'tables.*.capacity' => 'required|integer|min:1',
            'tables.*.price' => 'required|numeric|min:0',
            'tables.*.sell_as_whole' => 'boolean',
            'tables.*.position_x' => 'nullable|integer',
            'tables.*.position_y' => 'nullable|integer',
        ]);

        $tables = collect($request->input('tables'))->map(function ($tableData) use ($event) {
            return Table::create([
                'event_id' => $event->id,
                ...$tableData,
            ]);
        });

        return response()->json([
            'message' => count($tables) . ' tables created successfully',
            'tables' => TableResource::collection($tables),
        ], 201);
    }
}
