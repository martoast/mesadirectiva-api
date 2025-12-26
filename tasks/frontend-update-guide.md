# Frontend Update Guide: Simplified Event System

## Summary

We simplified the event creation process to match Eventbrite's approach. The goal is fewer fields, cleaner UX, and support for online events.

---

## What Changed

### 1. Event Fields (Simplified)

**Before:** 26+ fields including separate hero section, about section, schedule, highlights, venue details, etc.

**Now:** ~12 core fields

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Event name (required) |
| `description` | string | Rich HTML description |
| `image` | string | Main event image (URL or uploaded) |
| `starts_at` | datetime | Event start (required) |
| `ends_at` | datetime | Event end (required) |
| `timezone` | string | Default: `America/Los_Angeles` |
| `location_type` | enum | `venue` or `online` |
| `location` | object | Location details (see below) |
| `media` | object | Gallery images + YouTube videos |
| `organizer_name` | string | Organizer display name |
| `organizer_description` | string | About the organizer |
| `faq_items` | array | Q&A items |
| `is_private` | boolean | Hide from public listings |
| `show_remaining` | boolean | Show tickets remaining |

**Removed fields:** `date`, `time`, `price`, `max_tickets`, `tickets_sold`, `registration_open`, `registration_deadline`, `hero_*`, `about_*`, `highlights`, `schedule`, `gallery_images`, `venue_*`, `contact_*`

---

### 2. Location Types (NEW)

Events can now be **venue** (physical) or **online** (virtual).

**Venue Event:**
```json
{
  "location_type": "venue",
  "location": {
    "name": "Grand Ballroom",
    "address": "123 Main St",
    "city": "Los Angeles",
    "state": "CA",
    "country": "USA",
    "postal_code": "90001",
    "map_url": "https://maps.google.com/..."
  }
}
```

**Online Event:**
```json
{
  "location_type": "online",
  "location": {
    "platform": "Zoom",
    "url": "https://zoom.us/j/123456789",
    "instructions": "Link will be sent 1 hour before the event"
  }
}
```

---

### 3. Date/Time (Changed)

**Before:** Separate `date` and `time` fields
**Now:** Combined `starts_at` and `ends_at` datetime fields with `timezone`

```json
{
  "starts_at": "2025-02-15T18:00:00",
  "ends_at": "2025-02-15T23:00:00",
  "timezone": "America/Los_Angeles"
}
```

---

### 4. Ticket Tiers (Eventbrite-Style)

**Before:** Single price with optional `early_bird_price` and `early_bird_deadline`

**Now:** Each tier has its own **sales window** (start/end dates). Create separate tiers for early bird pricing.

```json
{
  "name": "Early Bird",
  "price": 35.00,
  "quantity": 100,
  "sales_start": "2025-01-01T00:00:00Z",
  "sales_end": "2025-01-15T23:59:59Z",
  "min_per_order": 1,
  "max_per_order": 4,
  "show_description": true,
  "is_hidden": false
}
```

**New tier fields:**
| Field | Description |
|-------|-------------|
| `quantity` | Total available (renamed from `max_quantity`) |
| `sales_start` | When sales begin (null = immediately) |
| `sales_end` | When sales end (null = until event) |
| `min_per_order` | Minimum tickets per order (default: 1) |
| `max_per_order` | Maximum tickets per order (default: 10) |
| `show_description` | Display description to customers |
| `is_hidden` | Hide tier from public (admin only) |

**New computed fields in response:**
- `sales_status`: `on_sale`, `scheduled`, `ended`, `sold_out`, `inactive`, `hidden`
- `is_on_sale`: boolean
- `is_sold_out`: boolean
- `available`: tickets remaining

**Early Bird Example:**
Create 3 tiers with overlapping/sequential sales windows:
```
Early Bird:  $35, sales: now → +2 weeks
General:     $50, sales: +2 weeks → event start
VIP:         $100, sales: now → event start
```

---

### 5. Media Gallery (NEW)

**Before:** `gallery_images` array of URLs

**Now:** `media` object with images and videos

```json
{
  "media": {
    "images": [
      { "type": "upload", "path": "events/.../abc.jpg", "url": "https://s3..." },
      { "type": "url", "url": "https://example.com/image.jpg" }
    ],
    "videos": [
      { "type": "youtube", "url": "https://youtube.com/...", "video_id": "dQw4w9WgXcQ" }
    ]
  }
}
```

---

## New/Changed API Endpoints

### Event Image
```
POST /events/{slug}/image
Content-Type: multipart/form-data
Body: image file
```

### Add Gallery Media
```
POST /events/{slug}/media

# For image upload:
type=image, file={multipart}

# For image URL:
type=image, url=https://...

# For YouTube:
type=youtube, url=https://youtube.com/watch?v=...
```

### Remove Gallery Media
```
DELETE /events/{slug}/media
Body: { "type": "images|videos", "index": 0 }
```

### Removed Endpoints
- `POST /events/{slug}/toggle-registration` - No longer needed
- `POST /events/{slug}/hero-image` - Renamed to `/image`

---

## Event Creation Form (Suggested Layout)

### Step 1: Basic Info
- Event name
- Description (rich text editor)
- Main image (upload or URL)

### Step 2: Date & Time
- Start date/time picker
- End date/time picker
- Timezone dropdown (default: America/Los_Angeles)

### Step 3: Location
- Toggle: Venue / Online
- **If Venue:** Name, Address, City, State, Country, Postal Code, Map URL
- **If Online:** Platform (Zoom, Google Meet, etc.), URL, Instructions

### Step 4: Tickets
- For each tier: Name, Price, Quantity, Sales Start, Sales End, Min/Max per order
- "Add Tier" button to create multiple tiers

### Step 5: Details (Optional)
- Organizer name & description
- FAQ items (add/remove)
- Media gallery (images + YouTube)
- Privacy settings (is_private, show_remaining)

---

## Response Changes

### EventResource
New fields in API response:
- `starts_at`, `ends_at`, `timezone`
- `location_type`, `location`, `location_name`, `location_address`
- `media`
- `organizer_name`, `organizer_description`
- `is_private`, `show_remaining`
- `total_tickets_sold`, `total_tickets_available`
- `available_ticket_tiers` (only tiers currently on sale)

### TicketTierResource
New fields:
- `quantity` (renamed from `max_quantity`)
- `sales_start`, `sales_end`, `sales_status`, `is_on_sale`
- `min_per_order`, `max_per_order`
- `show_description`, `is_hidden`, `is_sold_out`

Removed fields:
- `early_bird_price`, `early_bird_deadline`, `current_price`, `is_early_bird`

---

## Questions?

Refer to `tasks/summary.md` for complete API documentation.
