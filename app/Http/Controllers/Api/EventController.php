<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Http\Resources\OrderResource;
use App\Models\Event;
use App\Services\ImageService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private ImageService $imageService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Event::accessibleBy($request->user())
            ->with('group')
            ->orderBy('date', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        $events = $query->paginate($request->get('per_page', 15));

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

    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = Event::create([
            ...$request->validated(),
            'status' => 'draft',
            'tickets_sold' => 0,
            'registration_open' => true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Event created successfully',
            'event' => new EventResource($event->load('group')),
        ], 201);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)
            ->with(['group', 'items', 'creator'])
            ->firstOrFail();

        $this->authorize('view', $event);

        return response()->json([
            'event' => new EventResource($event),
        ]);
    }

    public function update(UpdateEventRequest $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $event->update($request->validated());

        return response()->json([
            'message' => 'Event updated successfully',
            'event' => new EventResource($event->load('group')),
        ]);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('delete', $event);

        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully',
        ]);
    }

    public function publish(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        if ($event->status === 'live') {
            return response()->json([
                'message' => 'Event is already live',
            ], 422);
        }

        // Create Stripe product and price if not exists
        if (!$event->stripe_product_id) {
            $stripeData = $this->stripeService->createEventProduct($event);
            $event->update([
                'stripe_product_id' => $stripeData['product_id'],
                'stripe_price_id' => $stripeData['price_id'],
            ]);
        }

        $event->update(['status' => 'live']);

        return response()->json([
            'message' => 'Event published successfully',
            'event' => new EventResource($event),
        ]);
    }

    public function close(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $event->update(['status' => 'closed']);

        return response()->json([
            'message' => 'Event closed successfully',
            'event' => new EventResource($event),
        ]);
    }

    public function toggleRegistration(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $event->update(['registration_open' => !$event->registration_open]);

        return response()->json([
            'message' => $event->registration_open ? 'Registration opened' : 'Registration closed',
            'event' => new EventResource($event),
        ]);
    }

    public function duplicate(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('create', Event::class);

        $newEvent = $event->replicate([
            'slug',
            'status',
            'tickets_sold',
            'stripe_product_id',
            'stripe_price_id',
        ]);

        $newEvent->name = $event->name . ' (Copy)';
        $newEvent->status = 'draft';
        $newEvent->tickets_sold = 0;
        $newEvent->created_by = $request->user()->id;
        $newEvent->save();

        // Duplicate items
        foreach ($event->items as $item) {
            $newItem = $item->replicate(['stripe_price_id']);
            $newItem->event_id = $newEvent->id;
            $newItem->quantity_sold = 0;
            $newItem->save();
        }

        return response()->json([
            'message' => 'Event duplicated successfully',
            'event' => new EventResource($newEvent->load('group', 'items')),
        ], 201);
    }

    public function uploadHeroImage(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,webp|max:5120',
        ]);

        // Delete old image if exists
        if ($event->hero_image) {
            $this->imageService->deleteImage($event->hero_image);
        }

        $path = $this->imageService->uploadHeroImage($request->file('image'), $event->slug);

        $event->update(['hero_image' => $path]);

        return response()->json([
            'message' => 'Image uploaded successfully',
            'path' => $path,
            'url' => $this->imageService->getUrl($path),
        ]);
    }

    public function orders(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('view', $event);

        $orders = $event->orders()
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'orders' => OrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }
}
