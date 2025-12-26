# Event Simplification Plan - Eventbrite Style

## Overview

Simplify the event creation process to match Eventbrite's streamlined approach. Remove over-engineered landing page builder fields and focus on essential event information.

---

## Decisions Made

| Question | Decision |
|----------|----------|
| Online events | YES - Support venue and online events |
| Early bird approach | Use Eventbrite-style: separate ticket tiers with sales windows |
| Gallery | KEEP - Support images (URL or upload) + YouTube video links |
| Schedule/Highlights | REMOVE - Over-engineered |
| Default timezone | America/Los_Angeles (PST) |

---

## Question 3 Clarification: Organizer Info

**The question:** When displaying "Organized by..." on the event page, where should this info come from?

**Option A: Store on Event (Recommended)**
```json
{
  "organizer_name": "Downtown School Foundation",
  "organizer_description": "We've been organizing community events since 2010..."
}
```
- Each event can have different organizer branding
- Flexible for organizations with multiple sub-groups
- User explicitly sets it per event

**Option B: Use Creator's Profile**
- Just show the `created_by` user's name/info
- Simpler, but less flexible
- Can't customize organizer branding per event

**Option C: Separate Organizer Model (Complex)**
- Create `organizers` table
- Users can create organizer profiles
- Events belong to an organizer
- Most flexible but adds complexity

**Recommendation:** Option A - simple fields on the event. Which do you prefer?

---

## Current vs Proposed Event Fields

### KEEP (Core Event Details)

| Field | Notes |
|-------|-------|
| `slug` | Auto-generated URL identifier |
| `group_id` | Organization/group ownership |
| `name` | Event title (required) |
| `description` | Event description (HTML allowed) |
| `status` | draft/live/closed |
| `created_by` | Creator reference |
| `timestamps` | Created/updated timestamps |
| `softDeletes` | Soft delete support |
| `seating_type` | general_admission/seated |
| `reservation_minutes` | For seated events |
| `faq_items` | Keep FAQs (JSON) |
| `stripe_product_id` | Stripe integration |
| `stripe_price_id` | Stripe integration |

### MODIFY

| Current | Proposed | Reason |
|---------|----------|--------|
| `date` + `time` | `starts_at` + `ends_at` | Full datetime with end time |
| `location` (string) | `location_type` + `location` (JSON) | Support online events |
| `hero_image` | `image` | Simpler naming |
| `gallery_images` | `media` (JSON) | Support images + YouTube videos |

### REMOVE

| Field | Reason |
|-------|--------|
| `price` | Lives on ticket tiers |
| `max_tickets` | Derived from ticket tiers |
| `tickets_sold` | Calculated from ticket tiers |
| `registration_open` | Use ticket sales dates |
| `registration_deadline` | Use ticket sales end dates |
| `hero_title` | Just use event `name` |
| `hero_subtitle` | Use `description` excerpt |
| `hero_cta_text` | Static "Get Tickets" |
| `about` | Redundant with `description` |
| `about_title` | Over-engineered |
| `about_content` | Over-engineered |
| `about_image` | Over-engineered |
| `about_image_position` | Over-engineered |
| `highlights` | Over-engineered |
| `schedule` | Over-engineered |
| `venue_name` | Merge into `location` JSON |
| `venue_address` | Merge into `location` JSON |
| `venue_map_url` | Merge into `location` JSON |
| `contact_email` | Use organizer info |
| `contact_phone` | Use organizer info |

### ADD

| Field | Type | Description |
|-------|------|-------------|
| `starts_at` | datetime | Event start (required) |
| `ends_at` | datetime | Event end (required) |
| `timezone` | string | Default: America/Los_Angeles |
| `location_type` | enum | "venue" or "online" |
| `location` | JSON | Venue or online details |
| `image` | string | Main event image |
| `media` | JSON | Gallery images + YouTube videos |
| `is_private` | boolean | Public or private listing |
| `show_remaining` | boolean | Show remaining tickets |
| `organizer_name` | string | Organizer display name |
| `organizer_description` | text | About the organizer |

---

## Proposed New Event Schema

```php
Schema::create('events', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->foreignId('group_id')->nullable()->constrained()->onDelete('set null');

    // Core Info
    $table->string('name');                              // Event title (required)
    $table->text('description')->nullable();             // HTML description
    $table->string('image')->nullable();                 // Main event image

    // Date/Time
    $table->dateTime('starts_at');                       // Event start (required)
    $table->dateTime('ends_at');                         // Event end (required)
    $table->string('timezone')->default('America/Los_Angeles');

    // Location
    $table->enum('location_type', ['venue', 'online'])->default('venue');
    $table->json('location')->nullable();                // Venue or online details

    // Media Gallery
    $table->json('media')->nullable();                   // Images + YouTube videos

    // Event Type
    $table->enum('seating_type', ['general_admission', 'seated'])->default('general_admission');
    $table->unsignedInteger('reservation_minutes')->default(15);

    // Settings
    $table->enum('status', ['draft', 'live', 'closed'])->default('draft');
    $table->boolean('is_private')->default(false);       // Public or private
    $table->boolean('show_remaining')->default(true);    // Show remaining tickets

    // Organizer
    $table->string('organizer_name')->nullable();
    $table->text('organizer_description')->nullable();

    // Optional Content
    $table->json('faq_items')->nullable();               // [{question, answer}]

    // Stripe
    $table->string('stripe_product_id')->nullable();
    $table->string('stripe_price_id')->nullable();

    // Metadata
    $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
    $table->timestamps();
    $table->softDeletes();

    // Indexes
    $table->index(['status', 'is_private']);
    $table->index('group_id');
    $table->index('starts_at');
});
```

---

## Media Gallery Structure

The `media` JSON field supports both images and YouTube videos:

```json
{
  "images": [
    {
      "type": "upload",
      "path": "events/gala-2025/gallery/img1.jpg",
      "url": "https://s3.../events/gala-2025/gallery/img1.jpg"
    },
    {
      "type": "url",
      "url": "https://example.com/external-image.jpg"
    }
  ],
  "videos": [
    {
      "type": "youtube",
      "url": "https://www.youtube.com/watch?v=abc123",
      "video_id": "abc123"
    }
  ]
}
```

---

## Location JSON Examples

**Venue Event:**
```json
{
  "name": "Grand Ballroom",
  "address": "123 Main Street",
  "city": "Los Angeles",
  "state": "CA",
  "country": "USA",
  "postal_code": "90001",
  "map_url": "https://maps.google.com/..."
}
```

**Online Event:**
```json
{
  "platform": "Zoom",
  "url": "https://zoom.us/j/123456",
  "instructions": "Link will be sent 1 hour before event"
}
```

---

## Enhanced Ticket Tiers Schema

Eventbrite-style ticket management with sales windows:

```php
Schema::create('ticket_tiers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->onDelete('cascade');

    // Core
    $table->string('name');                              // Ticket name (required)
    $table->text('description')->nullable();
    $table->decimal('price', 10, 2);                     // 0 = free
    $table->unsignedInteger('quantity')->nullable();     // null = unlimited
    $table->unsignedInteger('quantity_sold')->default(0);

    // Sales Window (Eventbrite-style early bird support)
    $table->dateTime('sales_start')->nullable();         // When tickets go on sale
    $table->dateTime('sales_end')->nullable();           // When ticket sales end

    // Limits
    $table->unsignedInteger('min_per_order')->default(1);
    $table->unsignedInteger('max_per_order')->default(10);

    // Display
    $table->boolean('show_description')->default(false); // Show on event page
    $table->boolean('is_hidden')->default(false);        // Hidden ticket type
    $table->unsignedInteger('sort_order')->default(0);
    $table->boolean('is_active')->default(true);

    $table->timestamps();

    $table->index(['event_id', 'is_active']);
    $table->index(['event_id', 'sort_order']);
});
```

### Early Bird Example (Eventbrite Style)

Instead of `early_bird_price` on one tier, create two tiers:

```json
[
  {
    "name": "Early Bird",
    "price": 80.00,
    "quantity": 100,
    "sales_start": "2025-01-01T00:00:00",
    "sales_end": "2025-03-01T23:59:59",
    "sort_order": 1
  },
  {
    "name": "General Admission",
    "price": 120.00,
    "quantity": 400,
    "sales_start": "2025-03-02T00:00:00",
    "sales_end": "2025-06-14T18:00:00",
    "sort_order": 2
  }
]
```

**Benefits:**
- Clear visibility of what tickets are available when
- Can have multiple early bird phases
- More intuitive than hidden early_bird_price field

---

## API Changes

### Create Event (Before: 26+ fields → After: ~12 fields)

**After:**
```json
{
  "group_id": 1,
  "name": "Annual Gala 2025",
  "description": "<p>Join us for an unforgettable evening...</p>",
  "image": "https://...",
  "starts_at": "2025-06-15T18:00:00",
  "ends_at": "2025-06-15T23:00:00",
  "timezone": "America/Los_Angeles",
  "location_type": "venue",
  "location": {
    "name": "Grand Ballroom",
    "address": "123 Main St",
    "city": "Los Angeles",
    "state": "CA"
  },
  "organizer_name": "School Foundation",
  "organizer_description": "Organizing community events since 2010",
  "is_private": false,
  "show_remaining": true
}
```

### Create Ticket Tier

```json
{
  "name": "Early Bird",
  "description": "Limited time pricing - save $40!",
  "price": 80.00,
  "quantity": 100,
  "sales_start": "2025-01-01T00:00:00",
  "sales_end": "2025-03-01T23:59:59",
  "min_per_order": 1,
  "max_per_order": 4,
  "show_description": true
}
```

### Add Media to Event

**Upload image:**
```
POST /events/{slug}/media
Content-Type: multipart/form-data
{
  "type": "image",
  "file": <image file>
}
```

**Add URL image:**
```
POST /events/{slug}/media
{
  "type": "image",
  "url": "https://example.com/image.jpg"
}
```

**Add YouTube video:**
```
POST /events/{slug}/media
{
  "type": "youtube",
  "url": "https://www.youtube.com/watch?v=abc123"
}
```

---

## Summary of Changes

| Category | Removed | Added | Net Change |
|----------|---------|-------|------------|
| Event Fields | 18 | 10 | **-8 fields** |
| Ticket Fields | 2 | 6 | +4 fields |
| **Total** | **20** | **16** | **-4 fields** |

**Key Simplifications:**
- Event creation: 26+ fields → ~12 fields
- Removed redundant hero/about sections
- Unified media gallery (images + videos)
- Eventbrite-style ticket sales windows
- Support for online events
- Clean location JSON structure

---

## Files to Update

### Database Migrations
- [ ] Create `simplify_events_table` migration
- [ ] Create `update_ticket_tiers_table` migration

### Models
- [ ] `app/Models/Event.php` - Update fillable, casts, remove old methods
- [ ] `app/Models/TicketTier.php` - Update fillable, casts, add new methods

### Controllers
- [ ] `app/Http/Controllers/Api/EventController.php` - Add media endpoints
- [ ] `app/Http/Controllers/Api/TicketTierController.php` - Update for new fields

### Requests
- [ ] `app/Http/Requests/StoreEventRequest.php` - Simplify validation
- [ ] `app/Http/Requests/UpdateEventRequest.php` - Simplify validation

### Resources
- [ ] `app/Http/Resources/EventResource.php` - Update output
- [ ] `app/Http/Resources/TicketTierResource.php` - Update output

### Seeders
- [ ] `database/seeders/EventSeeder.php` - Update sample data

### Documentation
- [ ] `tasks/summary.md` - Update API docs

---

## Implementation Complete!

**Organizer Info:** Implemented Option A - `organizer_name` and `organizer_description` fields on each event.

### What was implemented:

1. **Database Migrations**
   - `2025_12_25_233833_simplify_events_table.php` - Simplified events table
   - `2025_12_25_233920_update_ticket_tiers_for_eventbrite_style.php` - Added sales windows

2. **Models Updated**
   - `Event.php` - New fields, media helpers, location helpers
   - `TicketTier.php` - Sales window logic, availability checks

3. **Controllers Updated**
   - `EventController.php` - New media endpoints (`uploadImage`, `addMedia`, `removeMedia`)

4. **Requests Updated**
   - `StoreEventRequest.php` / `UpdateEventRequest.php` - Simplified validation
   - `StoreTicketTierRequest.php` / `UpdateTicketTierRequest.php` - Sales window validation

5. **Resources Updated**
   - `EventResource.php` - New output format
   - `TicketTierResource.php` - Sales status info

6. **Seeder Updated**
   - `EventSeeder.php` - 4 sample events (venue, seated, online, draft)

7. **Routes Updated**
   - Added `/events/{slug}/image` (upload main image)
   - Added `/events/{slug}/media` (add gallery media)
   - Added `DELETE /events/{slug}/media` (remove gallery media)
   - Removed `/events/{slug}/toggle-registration` (no longer needed)
