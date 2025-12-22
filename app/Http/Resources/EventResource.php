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
            'name' => $this->name,
            'description' => $this->description,
            'date' => $this->date->format('Y-m-d'),
            'time' => $this->time,
            'location' => $this->location,
            'price' => (float) $this->price,
            'max_tickets' => $this->max_tickets,
            'tickets_sold' => $this->tickets_sold,
            'tickets_available' => $this->getTicketsAvailable(),
            'status' => $this->status,
            'registration_open' => $this->registration_open,
            'registration_deadline' => $this->registration_deadline,
            'can_purchase' => $this->canPurchase(),
            'purchase_blocked_reason' => $this->getPurchaseBlockedReason(),

            // Hero Section
            'hero_title' => $this->hero_title,
            'hero_subtitle' => $this->hero_subtitle,
            'hero_image' => $this->hero_image,
            'hero_image_url' => $this->getImageUrl($this->hero_image),
            'hero_cta_text' => $this->hero_cta_text,

            // About Section
            'about' => $this->about,
            'about_title' => $this->about_title,
            'about_content' => $this->about_content,
            'about_image' => $this->about_image,
            'about_image_url' => $this->getImageUrl($this->about_image),
            'about_image_position' => $this->about_image_position,

            // Rich Content Sections
            'highlights' => $this->highlights,
            'schedule' => $this->schedule,
            'gallery_images' => $this->gallery_images,
            'faq_items' => $this->faq_items,

            // Venue & Contact
            'venue_name' => $this->venue_name,
            'venue_address' => $this->venue_address,
            'venue_map_url' => $this->venue_map_url,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,

            // Relationships
            'category' => new CategoryResource($this->whenLoaded('category')),
            'items' => EventItemResource::collection($this->whenLoaded('items')),
            'active_items' => EventItemResource::collection($this->whenLoaded('activeItems')),
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
