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
use Illuminate\Support\Str;

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
            ->orderBy('starts_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        if ($request->boolean('upcoming')) {
            $query->upcoming();
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
            ->with(['group', 'items', 'creator', 'ticketTiers'])
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

    public function duplicate(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('create', Event::class);

        $newEvent = $event->replicate([
            'slug',
            'status',
            'stripe_product_id',
            'stripe_price_id',
        ]);

        $newEvent->name = $event->name . ' (Copy)';
        $newEvent->status = 'draft';
        $newEvent->created_by = $request->user()->id;
        $newEvent->save();

        // Duplicate items
        foreach ($event->items as $item) {
            $newItem = $item->replicate(['stripe_price_id']);
            $newItem->event_id = $newEvent->id;
            $newItem->quantity_sold = 0;
            $newItem->save();
        }

        // Duplicate ticket tiers
        foreach ($event->ticketTiers as $tier) {
            $newTier = $tier->replicate();
            $newTier->event_id = $newEvent->id;
            $newTier->quantity_sold = 0;
            $newTier->save();
        }

        return response()->json([
            'message' => 'Event duplicated successfully',
            'event' => new EventResource($newEvent->load('group', 'items', 'ticketTiers')),
        ], 201);
    }

    public function uploadImage(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,webp|max:5120',
        ]);

        // Delete old image if exists
        if ($event->image) {
            $this->imageService->deleteImage($event->image);
        }

        $path = $this->imageService->uploadHeroImage($request->file('image'), $event->slug);

        $event->update(['image' => $path]);

        return response()->json([
            'message' => 'Image uploaded successfully',
            'path' => $path,
            'url' => $this->imageService->getUrl($path),
        ]);
    }

    public function addMedia(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $request->validate([
            'type' => 'required|in:image,youtube',
            'file' => 'required_if:type,image|image|mimes:jpeg,png,webp|max:5120',
            'url' => 'required_without:file|nullable|url',
        ]);

        $type = $request->type;

        if ($type === 'image') {
            if ($request->hasFile('file')) {
                // Upload image file
                $path = $this->imageService->uploadGalleryImage(
                    $request->file('file'),
                    $event->slug
                );
                $event->addImage([
                    'type' => 'upload',
                    'path' => $path,
                    'url' => $this->imageService->getUrl($path),
                ]);
            } else {
                // Add URL image
                $event->addImage([
                    'type' => 'url',
                    'url' => $request->url,
                ]);
            }
        } elseif ($type === 'youtube') {
            $url = $request->url;
            $videoId = $this->extractYoutubeVideoId($url);

            $event->addVideo([
                'type' => 'youtube',
                'url' => $url,
                'video_id' => $videoId,
            ]);
        }

        return response()->json([
            'message' => 'Media added successfully',
            'media' => $event->fresh()->media,
        ]);
    }

    public function removeMedia(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $this->authorize('update', $event);

        $request->validate([
            'type' => 'required|in:images,videos',
            'index' => 'required|integer|min:0',
        ]);

        $type = $request->type;
        $index = $request->index;

        // If it's an uploaded image, delete the file
        $media = $event->media ?? ['images' => [], 'videos' => []];
        if ($type === 'images' && isset($media['images'][$index])) {
            $item = $media['images'][$index];
            if ($item['type'] === 'upload' && isset($item['path'])) {
                $this->imageService->deleteImage($item['path']);
            }
        }

        $event->removeMediaItem($type, $index);

        return response()->json([
            'message' => 'Media removed successfully',
            'media' => $event->fresh()->media,
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

    private function extractYoutubeVideoId(string $url): ?string
    {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
