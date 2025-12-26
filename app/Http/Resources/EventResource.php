<?php

namespace App\Http\Resources;

use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,

            // Core Info
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'image_url' => $this->getImageUrl($this->image),

            // Date/Time
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'timezone' => $this->timezone,

            // Location
            'location_type' => $this->location_type,
            'location' => $this->location,
            'location_name' => $this->getLocationName(),
            'location_address' => $this->getLocationAddress(),

            // Media Gallery
            'media' => $this->media,

            // Event Type
            'seating_type' => $this->seating_type ?? 'general_admission',
            'reservation_minutes' => $this->reservation_minutes ?? 15,

            // Settings
            'status' => $this->status,
            'is_private' => $this->is_private,
            'show_remaining' => $this->show_remaining,

            // Organizer
            'organizer_name' => $this->organizer_name,
            'organizer_description' => $this->organizer_description,

            // Content
            'faq_items' => $this->faq_items,

            // Computed Fields
            'can_purchase' => $this->canPurchase(),
            'purchase_blocked_reason' => $this->getPurchaseBlockedReason(),
            'total_tickets_sold' => $this->getTotalTicketsSold(),
            'total_tickets_available' => $this->when(
                $this->show_remaining,
                fn() => $this->getTotalTicketsAvailable()
            ),

            // Relationships
            'group' => new GroupResource($this->whenLoaded('group')),
            'items' => EventItemResource::collection($this->whenLoaded('items')),
            'active_items' => EventItemResource::collection($this->whenLoaded('activeItems')),
            'ticket_tiers' => TicketTierResource::collection($this->whenLoaded('ticketTiers')),
            'active_ticket_tiers' => TicketTierResource::collection($this->whenLoaded('activeTicketTiers')),
            'available_ticket_tiers' => TicketTierResource::collection($this->whenLoaded('availableTicketTiers')),
            'tables' => TableResource::collection($this->whenLoaded('tables')),
            'active_tables' => TableResource::collection($this->whenLoaded('activeTables')),
            'created_by' => $this->created_by,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get full URL for an image field (handles both S3 paths and external URLs)
     */
    private function getImageUrl(?string $image): ?string
    {
        if (!$image) {
            return null;
        }

        // If it's already a full URL, return as-is
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        // Otherwise, get URL from S3
        return app(ImageService::class)->getUrl($image);
    }
}
