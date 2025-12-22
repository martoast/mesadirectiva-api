<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventItemRequest;
use App\Http\Resources\EventItemResource;
use App\Models\Event;
use App\Models\EventItem;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventItemController extends Controller
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    public function index(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('view', $event);

        return response()->json([
            'items' => EventItemResource::collection($event->items),
        ]);
    }

    public function store(StoreEventItemRequest $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $item = EventItem::create([
            'event_id' => $event->id,
            ...$request->validated(),
            'quantity_sold' => 0,
        ]);

        // Create Stripe price if event has a product
        if ($event->stripe_product_id) {
            $priceId = $this->stripeService->createItemPrice($item, $event->stripe_product_id);
            $item->update(['stripe_price_id' => $priceId]);
        }

        return response()->json([
            'message' => 'Item created successfully',
            'item' => new EventItemResource($item),
        ], 201);
    }

    public function update(StoreEventItemRequest $request, string $slug, int $itemId): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $item = EventItem::where('event_id', $event->id)
            ->where('id', $itemId)
            ->firstOrFail();

        $item->update($request->validated());

        return response()->json([
            'message' => 'Item updated successfully',
            'item' => new EventItemResource($item),
        ]);
    }

    public function destroy(Request $request, string $slug, int $itemId): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $item = EventItem::where('event_id', $event->id)
            ->where('id', $itemId)
            ->firstOrFail();

        $item->delete();

        return response()->json([
            'message' => 'Item deleted successfully',
        ]);
    }
}
