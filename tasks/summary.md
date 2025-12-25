# Event Ticketing API - Complete Documentation

## Overview

REST API backend for an event ticketing platform. Supports two mutually exclusive event types: **General Admission** (with ticket tiers and early bird pricing) and **Seated Events** (with tables and seat selection). Includes ticket sales via Stripe Checkout, role-based access control with category-based permissions, and Excel-exportable reports.

### Event Types

| Event Type | Description |
|------------|-------------|
| **General Admission** | Ticket tiers (e.g., General, VIP, Premium) with optional early bird pricing. Quantity-based purchasing. |
| **Seated** | Tables and individual seats. Customers select specific tables or seats to purchase. Includes reservation system during checkout. |

### Tech Stack
- **Framework:** Laravel 12 (PHP 8.4)
- **Database:** MySQL 8.4
- **Authentication:** Laravel Sanctum (token-based)
- **Payments:** Stripe Checkout
- **File Storage:** AWS S3
- **Excel Exports:** Maatwebsite Excel

### Base URL
```
http://localhost:8001
```

### Authentication
All protected endpoints require a Bearer token in the Authorization header:
```
Authorization: Bearer {token}
```

---

## User Roles & Permissions

| Role | Description |
|------|-------------|
| `super_admin` | Full access to all events, users, categories, and settings |
| `admin` | Access only to events within assigned categories |
| `viewer` | Read-only access to events within assigned categories |

### Category Permissions (for admin/viewer roles)
| Permission | Capabilities |
|------------|-------------|
| `view` | Read-only access to category's events |
| `edit` | Can create/update events in category |
| `manage` | Full control including delete |

---

## Data Models

### User
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "avatar": "https://...",
  "role": "super_admin|admin|viewer",
  "is_active": true,
  "email_verified_at": "2024-01-01T00:00:00Z",
  "categories": [CategoryResource],
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

### Category
```json
{
  "id": 1,
  "name": "Primaria",
  "slug": "primaria",
  "description": "Primary school events",
  "color": "#22c55e",
  "events_count": 5,
  "permission": "view|edit|manage",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

### Event
```json
{
  "id": 1,
  "slug": "annual-gala-2024",
  "name": "Annual Gala 2024",
  "description": "Our annual fundraising gala",
  "date": "2024-06-15",
  "time": "18:00",
  "location": "Grand Ballroom, Hotel XYZ",
  "price": 150.00,
  "max_tickets": 500,
  "tickets_sold": 125,
  "tickets_available": 375,
  "status": "draft|live|closed",

  // Seating Configuration
  "seating_type": "general_admission|seated",
  "reservation_minutes": 15,

  "registration_open": true,
  "registration_deadline": "2024-06-10T23:59:59Z",
  "can_purchase": true,
  "purchase_blocked_reason": null,

  // Hero Section
  "hero_title": "Annual Gala 2024",
  "hero_subtitle": "Join us for an unforgettable evening",
  "hero_image": "events/annual-gala-2024/hero-abc123.jpg",
  "hero_image_url": "https://s3.../events/annual-gala-2024/hero-abc123.jpg",
  "hero_cta_text": "Get Your Tickets",

  // About Section
  "about": "Short description text",
  "about_title": "A Night to Remember",
  "about_content": "<p>Rich <strong>HTML content</strong> for detailed description...</p>",
  "about_image": "https://example.com/about-image.jpg",
  "about_image_url": "https://example.com/about-image.jpg",
  "about_image_position": "right",

  // Rich Content Sections (all optional)
  "highlights": [
    { "icon": "utensils", "title": "Gourmet Dining", "description": "Five-course meal" }
  ],
  "schedule": [
    { "time": "6:00 PM", "title": "Doors Open", "description": "Welcome reception" }
  ],
  "gallery_images": [
    "https://example.com/image1.jpg",
    "https://example.com/image2.jpg"
  ],
  "faq_items": [
    { "question": "What is the dress code?", "answer": "Black tie optional." }
  ],

  // Venue & Contact (all optional)
  "venue_name": "Grand Ballroom at Hotel Marriott",
  "venue_address": "123 Main Street, City, ST 12345",
  "venue_map_url": "https://maps.google.com/?q=...",
  "contact_email": "events@example.com",
  "contact_phone": "+1 (555) 123-4567",

  // Relationships
  "category": CategoryResource,
  "items": [EventItemResource],
  "active_items": [EventItemResource],
  "ticket_tiers": [TicketTierResource],
  "active_ticket_tiers": [TicketTierResource],
  "tables": [TableResource],
  "active_tables": [TableResource],
  "created_by": 1,
  "creator": UserResource,
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

**Event Status Flow:**
- `draft` → Initial state, not visible publicly
- `live` → Published and accepting orders (creates Stripe product)
- `closed` → No longer accepting orders

**Seating Types:**
- `general_admission` (default) → Uses ticket tiers for pricing
- `seated` → Uses tables and seats for selection

**Purchase Blocked Reasons:**
- `not_live` - Event is not published
- `registration_closed` - Registration manually closed
- `deadline_passed` - Past registration deadline
- `sold_out` - No tickets available

---

### TicketTier (General Admission Events)

Ticket pricing tiers with optional early bird discounts.

```json
{
  "id": 1,
  "event_id": 1,
  "name": "VIP",
  "description": "Front row seating with complimentary drinks",
  "price": 200.00,
  "early_bird_price": 150.00,
  "early_bird_deadline": "2024-05-01T23:59:59Z",
  "current_price": 150.00,
  "is_early_bird": true,
  "max_quantity": 50,
  "quantity_sold": 10,
  "available_quantity": 40,
  "is_available": true,
  "sort_order": 1,
  "is_active": true,
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

**Pricing Logic:**
- If `early_bird_deadline` is set and `now() < early_bird_deadline`, use `early_bird_price`
- Otherwise, use `price`
- `current_price` and `is_early_bird` are computed fields

---

### Table (Seated Events)

Tables belonging to an event. Can be sold as a whole unit or have individual seats.

```json
{
  "id": 1,
  "event_id": 1,
  "name": "Table 1",
  "capacity": 8,
  "price": 1600.00,
  "sell_as_whole": true,
  "status": "available|reserved|sold",
  "position_x": 100,
  "position_y": 200,
  "is_active": true,
  "seats_count": 8,
  "available_seats_count": 0,
  "seats": [SeatResource],
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

**Table Purchase Modes:**

| sell_as_whole | Behavior |
|---------------|----------|
| `true` (default) | Entire table purchased as one unit at table price |
| `false` | Individual seats can be purchased, each seat has its own price |

---

### Seat (Seated Events)

Individual seats belonging to a table. Only used when `table.sell_as_whole = false`.

```json
{
  "id": 1,
  "table_id": 1,
  "label": "A1",
  "price": 200.00,
  "status": "available|reserved|sold",
  "position_x": 10,
  "position_y": 20,
  "is_active": true,
  "table": TableResource,
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

---

### Reservation (Seated Events)

Temporary holds during checkout process.

```json
{
  "token": "550e8400-e29b-41d4-a716-446655440000",
  "expires_at": "2024-01-01T12:15:00Z",
  "tables": [TableResource],
  "seats": [SeatResource]
}
```

**Reservation Flow:**
1. Customer selects tables/seats and calls reserve endpoint
2. Items are marked as "reserved" for `reservation_minutes` (default: 15)
3. Customer completes checkout with reservation token
4. On payment success: items marked as "sold"
5. On payment failure or expiration: items released back to "available"

---

### Event Landing Page Sections

All landing page fields are **optional** - admins can progressively enhance their event pages.

#### Hero Section
| Field | Type | Description |
|-------|------|-------------|
| `hero_title` | string | Main headline (required) |
| `hero_subtitle` | string | Secondary text (required) |
| `hero_image` | string | Background image URL |
| `hero_image_url` | string | Full URL for display (auto-generated) |
| `hero_cta_text` | string | Call-to-action button text (e.g., "Get Tickets") |

#### About Section
| Field | Type | Description |
|-------|------|-------------|
| `about` | string | Short description (required) |
| `about_title` | string | Section heading |
| `about_content` | string | Rich HTML content for detailed description |
| `about_image` | string | Image URL for about section |
| `about_image_url` | string | Full URL for display (auto-generated) |
| `about_image_position` | enum | `"left"` or `"right"` (default: right) |

#### Highlights Section
Feature cards to showcase key event highlights.
```json
"highlights": [
  {
    "icon": "utensils",      // Icon name (use FontAwesome or similar)
    "title": "Gourmet Dining",
    "description": "Five-course meal prepared by award-winning chefs"
  }
]
```
Max 10 items.

#### Schedule Section
Timeline/agenda for the event.
```json
"schedule": [
  {
    "time": "6:00 PM",
    "title": "Doors Open",
    "description": "Welcome reception with cocktails"
  }
]
```
Max 20 items.

#### Gallery Section
Array of image URLs for photo gallery.
```json
"gallery_images": [
  "https://example.com/photo1.jpg",
  "https://example.com/photo2.jpg"
]
```
Max 20 images.

#### FAQ Section
Common questions and answers.
```json
"faq_items": [
  {
    "question": "What is the dress code?",
    "answer": "Black tie optional. We encourage elegant attire."
  }
]
```
Max 20 items.

#### Venue & Contact
| Field | Type | Description |
|-------|------|-------------|
| `venue_name` | string | Venue name |
| `venue_address` | string | Full address |
| `venue_map_url` | string | Google Maps or similar URL |
| `contact_email` | email | Contact email for inquiries |
| `contact_phone` | string | Contact phone number |

---

### Image Handling

Both `hero_image` and `about_image` support two methods:

| Method | How | When to Use |
|--------|-----|-------------|
| **URL** | Pass image URL in create/update request | Quick setup, external images |
| **File Upload** | `POST /events/{slug}/hero-image` with multipart form | Custom images, auto-optimization |

The API returns two fields for each image:
- `hero_image` / `about_image` - The raw value (S3 path or external URL)
- `hero_image_url` / `about_image_url` - The full displayable URL (always use this)

File uploads are:
- Resized to max 1920x1080
- Converted to JPEG (85% quality)
- Stored on S3

### EventItem (Additional Purchasable Items)
```json
{
  "id": 1,
  "name": "VIP Upgrade",
  "description": "Includes premium seating and gift bag",
  "price": 50.00,
  "max_quantity": 100,
  "quantity_sold": 25,
  "available_quantity": 75,
  "is_active": true,
  "is_available": true,
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

### Order
```json
{
  "id": 1,
  "order_number": "ORD-240615-A1B2",
  "customer_name": "Jane Smith",
  "customer_email": "jane@example.com",
  "customer_phone": "+1234567890",
  "status": "pending|completed|failed|refunded",
  "subtotal": 200.00,
  "total": 200.00,
  "ticket_count": 2,
  "paid_at": "2024-01-01T12:00:00Z",
  "event": EventResource,
  "items": [OrderItemResource],
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

### OrderItem
```json
{
  "id": 1,
  "item_type": "ticket|extra_item",
  "item_id": null,
  "item_name": "Annual Gala 2024 - VIP",
  "quantity": 2,
  "unit_price": 150.00,
  "total_price": 300.00,
  "ticket_tier_id": 1,
  "seat_id": null,
  "table_id": null,
  "ticket_tier": TicketTierResource,
  "seat": SeatResource,
  "table": TableResource
}
```

**Order Item Types:**
- For General Admission: `ticket_tier_id` is set
- For Seated (tables): `table_id` is set
- For Seated (individual seats): `seat_id` is set
- For extra items: `item_id` is set

---

## API Endpoints

### Public Endpoints (No Auth Required)

#### API Info
```
GET /
```
**Response:**
```json
{
  "name": "mesadirectiva-api",
  "version": "1.0.0",
  "status": "ok"
}
```

---

### Authentication

#### Login
```
POST /auth/login
```
**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```
**Response (200):**
```json
{
  "user": UserResource,
  "token": "1|abc123..."
}
```
**Errors:**
- `422` - Invalid credentials
- `422` - Account deactivated

#### Logout
```
POST /auth/logout
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "message": "Logged out successfully"
}
```

#### Get Current User
```
GET /auth/user
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "user": UserResource (with categories)
}
```

#### Forgot Password
```
POST /auth/forgot-password
```
**Request Body:**
```json
{
  "email": "user@example.com"
}
```
**Response (200):**
```json
{
  "message": "Password reset link sent to your email."
}
```

#### Reset Password
```
POST /auth/reset-password
```
**Request Body:**
```json
{
  "token": "reset-token-from-email",
  "email": "user@example.com",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```
**Response (200):**
```json
{
  "message": "Password has been reset."
}
```

#### Google OAuth - Redirect
```
GET /auth/google/redirect
```
**Response:** Redirects to Google OAuth consent screen

#### Google OAuth - Callback
```
GET /auth/google/callback
```
**Response:** Redirects to frontend with token:
- Success: `{FRONTEND_URL}/auth/callback?token={token}`
- User not found: `{FRONTEND_URL}/login?error=user_not_found`
- Account deactivated: `{FRONTEND_URL}/login?error=account_deactivated`
- OAuth failed: `{FRONTEND_URL}/login?error=oauth_failed`

---

### Profile (Protected - Any Authenticated User)

#### Get Profile
```
GET /profile
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "avatar": null,
    "role": "admin",
    "is_active": true,
    "categories": [
      {
        "id": 1,
        "name": "Primaria",
        "slug": "primaria",
        "permission": "edit"
      }
    ],
    "created_at": "2024-01-01T00:00:00Z"
  }
}
```

#### Update Profile
```
PATCH /profile
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "name": "Updated Name",
  "phone": "+0987654321"
}
```
**Response (200):**
```json
{
  "message": "Profile updated successfully",
  "user": UserResource
}
```

#### Change Password
```
PUT /profile/password
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "current_password": "oldpassword123",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```
**Response (200):**
```json
{
  "message": "Password updated successfully"
}
```
**Errors:**
- `422` - Current password incorrect
- `422` - Password must be at least 8 characters
- `422` - Password confirmation doesn't match

#### Delete Account
```
DELETE /profile
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "message": "Account deleted successfully."
}
```

---

### Public Events

#### List Live Events
```
GET /public/events
```
**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `category` | string | Filter by category slug |
| `per_page` | int | Items per page (default: 12) |
| `page` | int | Page number |

**Response (200):**
```json
{
  "events": [EventResource],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 12,
    "total": 50
  }
}
```

#### Get Event Details
```
GET /public/events/{slug}
```
**Response (200):**
```json
{
  "event": EventResource (with category, activeItems)
}
```

#### Check Availability
```
GET /public/events/{slug}/availability
```

**Response for General Admission Events (200):**
```json
{
  "can_purchase": true,
  "blocked_reason": null,
  "seating_type": "general_admission",
  "registration_open": true,
  "registration_deadline": "2024-06-10T23:59:59Z",
  "tickets_available": 375,
  "tiers": [
    {
      "id": 1,
      "name": "General",
      "price": 100.00,
      "early_bird_price": 80.00,
      "current_price": 80.00,
      "is_early_bird": true,
      "early_bird_deadline": "2024-05-01T23:59:59Z",
      "available_quantity": 150,
      "is_available": true
    },
    {
      "id": 2,
      "name": "VIP",
      "price": 200.00,
      "current_price": 200.00,
      "is_early_bird": false,
      "available_quantity": 50,
      "is_available": true
    }
  ],
  "items": [
    {
      "id": 1,
      "name": "Parking Pass",
      "price": 25.00,
      "available": true,
      "available_quantity": 100
    }
  ]
}
```

**Response for Seated Events (200):**
```json
{
  "can_purchase": true,
  "blocked_reason": null,
  "seating_type": "seated",
  "registration_open": true,
  "registration_deadline": "2024-06-10T23:59:59Z",
  "tables_available": 10,
  "tables_total": 15,
  "seats_available": 25,
  "seats_total": 40,
  "items": [
    {
      "id": 1,
      "name": "Valet Parking",
      "price": 50.00,
      "available": true,
      "available_quantity": 50
    }
  ]
}
```

---

### Public Ticket Tiers (General Admission Events)

#### Get Available Ticket Tiers
```
GET /public/events/{slug}/ticket-tiers
```
**Response (200):**
```json
{
  "tiers": [TicketTierResource]
}
```
**Errors:**
- `400` - "This is a seated event. Use the tables endpoint instead."

---

### Public Tables & Seats (Seated Events)

#### Get Available Tables
```
GET /public/events/{slug}/tables
```
**Response (200):**
```json
{
  "tables": [TableResource (with seats)]
}
```
**Errors:**
- `400` - "This is a general admission event. Use the ticket-tiers endpoint instead."

#### Get Available Seats for a Table
```
GET /public/events/{slug}/tables/{tableId}/seats
```
**Response (200):**
```json
{
  "table": TableResource,
  "seats": [SeatResource]
}
```
**Errors:**
- `400` - "This table is sold as a whole. Individual seats are not available."

---

### Reservations (Seated Events)

#### Reserve Tables/Seats
```
POST /public/events/{slug}/reserve
```
**Request Body:**
```json
{
  "tables": [1, 2],
  "seats": [5, 6, 7]
}
```
**Response (201):**
```json
{
  "message": "Reservation created successfully",
  "reservation": {
    "token": "550e8400-e29b-41d4-a716-446655440000",
    "expires_at": "2024-01-01T12:15:00Z",
    "tables": [TableResource],
    "seats": [SeatResource]
  }
}
```
**Errors:**
- `400` - "Reservations are only available for seated events."
- `400` - "Table 'X' is not available"
- `400` - "Seat 'X' is not available"
- `400` - "Table 'X' must be purchased by individual seats" (when trying to reserve a table with `sell_as_whole=false`)

#### Release Reservation
```
DELETE /public/events/{slug}/reserve
```
**Request Body:**
```json
{
  "token": "550e8400-e29b-41d4-a716-446655440000"
}
```
**Response (200):**
```json
{
  "message": "Reservation released successfully"
}
```

---

### Checkout (Public)

#### Create Checkout Session

**For General Admission Events (with tiers):**
```
POST /checkout/create-session
```
**Request Body:**
```json
{
  "event_slug": "concert-2024",
  "customer_name": "Jane Smith",
  "customer_email": "jane@example.com",
  "customer_phone": "+1234567890",
  "tiers": [
    { "tier_id": 1, "quantity": 2 },
    { "tier_id": 2, "quantity": 1 }
  ],
  "extra_items": [
    { "item_id": 1, "quantity": 1 }
  ]
}
```

**For General Admission Events (legacy - single price):**
```json
{
  "event_slug": "concert-2024",
  "customer_name": "Jane Smith",
  "customer_email": "jane@example.com",
  "customer_phone": "+1234567890",
  "tickets": 3,
  "extra_items": []
}
```

**For Seated Events:**
```json
{
  "event_slug": "gala-2024",
  "customer_name": "Jane Smith",
  "customer_email": "jane@example.com",
  "customer_phone": "+1234567890",
  "tables": [1],
  "seats": [5, 6, 7],
  "reservation_token": "550e8400-e29b-41d4-a716-446655440000",
  "extra_items": [
    { "item_id": 1, "quantity": 2 }
  ]
}
```

**Response (200):**
```json
{
  "checkout_url": "https://checkout.stripe.com/...",
  "session_id": "cs_test_...",
  "order_number": "ORD-240615-A1B2"
}
```

**Errors:**
- `422` - Cannot purchase (with `reason` field)
- `422` - Not enough tickets available
- `422` - Item not available
- `422` - Invalid or expired reservation
- `422` - Ticket tier not available

---

### Stripe Webhook
```
POST /webhooks/stripe
```
Handles Stripe events:
- `checkout.session.completed` - Marks order as completed, updates inventory:
  - For General Admission: Increments tier `quantity_sold`
  - For Seated: Marks tables/seats as "sold", completes reservations
  - Sends confirmation email
- `payment_intent.payment_failed` - Marks order as failed
- `charge.refunded` - Marks order as refunded:
  - For General Admission: Decrements tier `quantity_sold`
  - For Seated: Releases tables/seats back to "available"

---

### Dashboard (Protected)

#### Get Overall Stats
```
GET /dashboard/stats
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "stats": {
    "total_events": 25,
    "live_events": 10,
    "total_orders": 500,
    "total_revenue": 75000.00,
    "tickets_sold_today": 15,
    "revenue_today": 2250.00
  }
}
```

#### Get Event Stats
```
GET /dashboard/events/{slug}/stats
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "event": EventResource,
  "tickets_sold": 125,
  "tickets_available": 375,
  "revenue": 18750.00,
  "orders_count": 75,
  "sales_by_day": [
    {
      "date": "2024-06-01",
      "orders": 10,
      "revenue": 1500.00
    }
  ],
  "extra_items_sold": [
    {
      "name": "VIP Upgrade",
      "quantity": 25,
      "revenue": 1250.00
    }
  ]
}
```

---

### Categories (Protected)

#### List Categories
```
GET /categories
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "categories": [CategoryResource (with events_count)]
}
```

#### Create Category (super_admin only)
```
POST /categories
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "name": "New Category",
  "description": "Category description",
  "color": "#3b82f6"
}
```
**Response (201):**
```json
{
  "message": "Category created successfully",
  "category": CategoryResource
}
```

#### Get Category
```
GET /categories/{id}
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "category": CategoryResource (with events_count)
}
```

#### Update Category (super_admin only)
```
PUT /categories/{id}
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "name": "Updated Name",
  "description": "Updated description",
  "color": "#22c55e"
}
```
**Response (200):**
```json
{
  "message": "Category updated successfully",
  "category": CategoryResource
}
```

#### Delete Category (super_admin only)
```
DELETE /categories/{id}
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "message": "Category deleted successfully"
}
```

---

### Events (Protected)

#### List Events
```
GET /events
Authorization: Bearer {token}
```
**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | Filter by status (draft/live/closed) |
| `category_id` | int | Filter by category |
| `per_page` | int | Items per page (default: 15) |
| `page` | int | Page number |

**Response (200):**
```json
{
  "events": [EventResource (with category)],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 35
  }
}
```

#### Create Event
```
POST /events
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "category_id": 1,
  "name": "New Event",
  "description": "Event description",
  "date": "2024-08-15",
  "time": "18:00",
  "location": "Event Venue",
  "price": 100.00,
  "max_tickets": 200,
  "seating_type": "general_admission",
  "reservation_minutes": 15,
  "hero_title": "Welcome to New Event",
  "hero_subtitle": "Don't miss this amazing opportunity",
  "hero_image": "https://example.com/image.jpg",
  "about": "Full event description and details...",
  "registration_deadline": "2024-08-10T23:59:59Z"
}
```

> **Note:** `hero_image` is optional. You can either:
> 1. Pass a URL here when creating/updating the event
> 2. Upload a file separately via `POST /events/{slug}/hero-image`

**Response (201):**
```json
{
  "message": "Event created successfully",
  "event": EventResource
}
```

#### Get Event
```
GET /events/{slug}
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "event": EventResource (with category, items, creator)
}
```

#### Update Event
```
PUT /events/{slug}
Authorization: Bearer {token}
```
**Request Body:** (all fields optional)
```json
{
  "name": "Updated Event Name",
  "description": "Updated description",
  "date": "2024-08-20",
  "time": "19:00",
  "location": "New Venue",
  "price": 125.00,
  "max_tickets": 250,
  "seating_type": "seated",
  "reservation_minutes": 20,
  "hero_title": "Updated Title",
  "hero_subtitle": "Updated subtitle",
  "hero_image": "https://example.com/new-image.jpg",
  "about": "Updated about section",
  "registration_deadline": "2024-08-15T23:59:59Z"
}
```
**Response (200):**
```json
{
  "message": "Event updated successfully",
  "event": EventResource
}
```

#### Delete Event (soft delete)
```
DELETE /events/{slug}
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "message": "Event deleted successfully"
}
```

#### Publish Event
```
POST /events/{slug}/publish
Authorization: Bearer {token}
```
Creates Stripe product/price and sets status to `live`.

**Response (200):**
```json
{
  "message": "Event published successfully",
  "event": EventResource
}
```

#### Close Event
```
POST /events/{slug}/close
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "message": "Event closed successfully",
  "event": EventResource
}
```

#### Toggle Registration
```
POST /events/{slug}/toggle-registration
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "message": "Registration opened|closed",
  "event": EventResource
}
```

#### Duplicate Event
```
POST /events/{slug}/duplicate
Authorization: Bearer {token}
```
Creates a copy of the event with status `draft`.

**Response (201):**
```json
{
  "message": "Event duplicated successfully",
  "event": EventResource (with items)
}
```

#### Upload Hero Image
```
POST /events/{slug}/hero-image
Authorization: Bearer {token}
Content-Type: multipart/form-data
```
**Request Body:**
- `image` - Image file (jpeg, png, webp, max 5MB)

**Response (200):**
```json
{
  "message": "Image uploaded successfully",
  "path": "events/annual-gala-2024/hero-xyz789.jpg",
  "url": "https://s3.../events/annual-gala-2024/hero-xyz789.jpg"
}
```

#### Get Event Orders
```
GET /events/{slug}/orders
Authorization: Bearer {token}
```
**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `per_page` | int | Items per page (default: 15) |
| `page` | int | Page number |

**Response (200):**
```json
{
  "orders": [OrderResource (with items)],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75
  }
}
```

---

### Ticket Tiers (Protected - General Admission Events)

#### List Ticket Tiers
```
GET /events/{slug}/ticket-tiers
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "tiers": [TicketTierResource]
}
```

#### Create Ticket Tier
```
POST /events/{slug}/ticket-tiers
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "name": "VIP",
  "description": "Front row seating with drinks",
  "price": 200.00,
  "early_bird_price": 150.00,
  "early_bird_deadline": "2024-05-01T23:59:59Z",
  "max_quantity": 50,
  "sort_order": 1,
  "is_active": true
}
```
**Response (201):**
```json
{
  "message": "Ticket tier created successfully",
  "tier": TicketTierResource
}
```

#### Get Ticket Tier
```
GET /events/{slug}/ticket-tiers/{tierId}
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "tier": TicketTierResource
}
```

#### Update Ticket Tier
```
PUT /events/{slug}/ticket-tiers/{tierId}
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "name": "Updated VIP",
  "price": 250.00,
  "is_active": true
}
```
**Response (200):**
```json
{
  "message": "Ticket tier updated successfully",
  "tier": TicketTierResource
}
```

#### Delete Ticket Tier
```
DELETE /events/{slug}/ticket-tiers/{tierId}
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "message": "Ticket tier deleted successfully"
}
```

---

### Tables (Protected - Seated Events)

#### List Tables
```
GET /events/{slug}/tables
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "tables": [TableResource (with seats)]
}
```

#### Create Table
```
POST /events/{slug}/tables
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "name": "Table 1",
  "capacity": 8,
  "price": 1600.00,
  "sell_as_whole": true,
  "position_x": 100,
  "position_y": 200,
  "is_active": true
}
```
**Response (201):**
```json
{
  "message": "Table created successfully",
  "table": TableResource
}
```

#### Bulk Create Tables
```
POST /events/{slug}/tables/bulk
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "tables": [
    {
      "name": "Table 1",
      "capacity": 8,
      "price": 1600.00,
      "sell_as_whole": true
    },
    {
      "name": "Table 2",
      "capacity": 6,
      "price": 1200.00,
      "sell_as_whole": false
    }
  ]
}
```
**Response (201):**
```json
{
  "message": "3 tables created successfully",
  "tables": [TableResource]
}
```

#### Get Table
```
GET /events/{slug}/tables/{tableId}
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "table": TableResource (with seats)
}
```

#### Update Table
```
PUT /events/{slug}/tables/{tableId}
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "name": "VIP Table 1",
  "price": 2000.00,
  "is_active": true
}
```
**Response (200):**
```json
{
  "message": "Table updated successfully",
  "table": TableResource
}
```

#### Delete Table
```
DELETE /events/{slug}/tables/{tableId}
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "message": "Table deleted successfully"
}
```
**Errors:**
- `422` - "Cannot delete table that has been sold"

---

### Seats (Protected - Seated Events)

#### List Seats for Table
```
GET /events/{slug}/tables/{tableId}/seats
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "seats": [SeatResource]
}
```

#### Create Seat
```
POST /events/{slug}/tables/{tableId}/seats
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "label": "A1",
  "price": 200.00,
  "position_x": 10,
  "position_y": 20,
  "is_active": true
}
```
**Response (201):**
```json
{
  "message": "Seat created successfully",
  "seat": SeatResource
}
```

#### Bulk Create Seats
```
POST /events/{slug}/tables/{tableId}/seats/bulk
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "seats": [
    { "label": "A1", "price": 200.00 },
    { "label": "A2", "price": 200.00 },
    { "label": "A3", "price": 250.00 }
  ]
}
```
**Response (201):**
```json
{
  "message": "3 seats created successfully",
  "seats": [SeatResource]
}
```

#### Update Seat
```
PUT /events/{slug}/seats/{seatId}
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "label": "A1-VIP",
  "price": 300.00,
  "is_active": true
}
```
**Response (200):**
```json
{
  "message": "Seat updated successfully",
  "seat": SeatResource
}
```

#### Delete Seat
```
DELETE /events/{slug}/seats/{seatId}
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "message": "Seat deleted successfully"
}
```
**Errors:**
- `422` - "Cannot delete seat that has been sold"

---

### Event Items (Protected)

#### List Event Items
```
GET /events/{slug}/items
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "items": [EventItemResource]
}
```

#### Create Event Item
```
POST /events/{slug}/items
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "name": "VIP Upgrade",
  "description": "Premium seating and gift bag",
  "price": 50.00,
  "max_quantity": 100,
  "is_active": true
}
```
**Response (201):**
```json
{
  "message": "Item created successfully",
  "item": EventItemResource
}
```

#### Update Event Item
```
PUT /events/{slug}/items/{itemId}
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "name": "Updated Item Name",
  "description": "Updated description",
  "price": 75.00,
  "max_quantity": 150,
  "is_active": true
}
```
**Response (200):**
```json
{
  "message": "Item updated successfully",
  "item": EventItemResource
}
```

#### Delete Event Item
```
DELETE /events/{slug}/items/{itemId}
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "message": "Item deleted successfully"
}
```

---

### Users (Protected - super_admin only)

#### List Users
```
GET /users
Authorization: Bearer {token}
```
**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `role` | string | Filter by role |
| `search` | string | Search name/email |
| `per_page` | int | Items per page (default: 15) |
| `page` | int | Page number |

**Response (200):**
```json
{
  "users": [UserResource (with categories)],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 15,
    "total": 25
  }
}
```

#### Create User
```
POST /users
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "name": "New User",
  "email": "newuser@example.com",
  "password": "password123",
  "role": "admin",
  "is_active": true
}
```
**Response (201):**
```json
{
  "message": "User created successfully",
  "user": UserResource
}
```

#### Get User
```
GET /users/{id}
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "user": UserResource (with categories)
}
```

#### Update User
```
PUT /users/{id}
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "name": "Updated Name",
  "email": "updated@example.com",
  "password": "newpassword123",
  "role": "viewer",
  "is_active": true
}
```
**Response (200):**
```json
{
  "message": "User updated successfully",
  "user": UserResource
}
```

#### Delete User (Deactivate)
```
DELETE /users/{id}
Authorization: Bearer {token}
```
Sets `is_active` to false (doesn't actually delete).

**Response (200):**
```json
{
  "message": "User deactivated successfully"
}
```

#### Assign Categories
```
POST /users/{id}/categories
Authorization: Bearer {token}
```
**Request Body:**
```json
{
  "categories": [
    { "id": 1, "permission": "edit" },
    { "id": 2, "permission": "view" },
    { "id": 3, "permission": "manage" }
  ]
}
```
**Response (200):**
```json
{
  "message": "Categories assigned successfully",
  "user": UserResource (with categories)
}
```

#### Toggle Active Status
```
POST /users/{id}/toggle-active
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "message": "User activated|deactivated",
  "user": UserResource
}
```

---

### Reports (Protected)

#### Get Sales Report
```
GET /reports/sales
Authorization: Bearer {token}
```
**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `event_id` | int | Filter by event |
| `category_id` | int | Filter by category |
| `date_from` | date | Start date (YYYY-MM-DD) |
| `date_to` | date | End date (YYYY-MM-DD) |
| `search` | string | Search customer name/email/order number |

**Response (200):**
```json
{
  "orders": [OrderResource],
  "summary": {
    "total_orders": 150,
    "total_revenue": 22500.00
  }
}
```

#### Export Sales to Excel
```
GET /reports/sales/export
Authorization: Bearer {token}
```
**Query Parameters:** Same as sales report

**Response:** Excel file download (`sales-report-YYYY-MM-DD.xlsx`)

#### Get Orders Report
```
GET /reports/orders
Authorization: Bearer {token}
```
**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `event_id` | int | Filter by event |
| `category_id` | int | Filter by category |
| `date_from` | date | Start date |
| `date_to` | date | End date |
| `status` | string | Filter by status |
| `search` | string | Search customer/order number |

**Response (200):**
```json
{
  "orders": [OrderResource]
}
```

#### Export Orders to Excel
```
GET /reports/orders/export
Authorization: Bearer {token}
```
**Query Parameters:** Same as orders report

**Response:** Excel file download (`orders-report-YYYY-MM-DD.xlsx`)

---

### Orders (Protected)

#### Get Order by Number
```
GET /orders/{orderNumber}
Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "order": OrderResource (with event, items)
}
```

---

## Checkout Flows

### General Admission Checkout Flow

1. **Frontend** calls `GET /public/events/{slug}/availability` to get available tiers
2. **Frontend** displays tier options with current prices (early bird or regular)
3. **Customer** selects quantities for each tier
4. **Frontend** calls `POST /checkout/create-session` with `tiers` array
5. **API** validates tier availability and creates pending order
6. **API** creates Stripe Checkout Session with line items for each tier
7. **Customer** completes payment on Stripe
8. **Stripe** sends webhook; API increments `quantity_sold` for each tier
9. **Customer** redirected to success page

### Seated Event Checkout Flow

1. **Frontend** calls `GET /public/events/{slug}/tables` to get seating map
2. **Customer** selects tables (whole) or individual seats
3. **Frontend** calls `POST /public/events/{slug}/reserve` to hold selections
4. **API** returns `reservation_token` valid for `reservation_minutes`
5. **Customer** enters contact info
6. **Frontend** calls `POST /checkout/create-session` with `tables`, `seats`, and `reservation_token`
7. **API** validates reservation and creates Stripe Checkout Session
8. **Customer** completes payment on Stripe
9. **Stripe** sends webhook; API marks tables/seats as "sold"
10. **If payment fails or reservation expires:** items released back to "available"

---

## Scheduled Tasks

### Expire Reservations
```
php artisan reservations:expire
```
Runs every minute via Laravel scheduler. Releases expired seat/table reservations back to "available" status.

**Setup (server cron):**
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Error Responses

All errors follow this format:
```json
{
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

Common HTTP Status Codes:
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthenticated
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## Environment Variables (Frontend Needs)

```env
# API Base URL
VITE_API_URL=http://localhost:8001

# Stripe Public Key (for client-side)
VITE_STRIPE_KEY=pk_test_xxx

# Google OAuth (if implementing direct OAuth)
VITE_GOOGLE_CLIENT_ID=xxx
```

---

## Database Schema Overview

```
┌─────────────┐      ┌──────────────────┐      ┌─────────────────┐
│   users     │      │    categories    │      │     events      │
├─────────────┤      ├──────────────────┤      ├─────────────────┤
│ id          │◄──┐  │ id               │◄──┐  │ id              │
│ name        │   │  │ name             │   │  │ slug            │
│ email       │   │  │ slug             │   │  │ name            │
│ role        │   │  │ color            │   │  │ seating_type    │
│ is_active   │   │  └──────────────────┘   │  │ status          │
└─────────────┘   │                          │  │ category_id ────┘
       │          │                          │  │ created_by ─────┘
       │          │                          │  └─────────────────┘
       │          │                          │           │
       └──────────┴──────────────────────────┘           │
                                                         │
    ┌────────────────────────────────────────────────────┴───────────────────────────────────┐
    │                                                                                         │
    ▼                                    ▼                                    ▼               ▼
┌─────────────────┐              ┌─────────────────┐              ┌─────────────────┐   ┌──────────────┐
│  ticket_tiers   │              │     tables      │              │  event_items    │   │    orders    │
├─────────────────┤              ├─────────────────┤              ├─────────────────┤   ├──────────────┤
│ id              │              │ id              │              │ id              │   │ id           │
│ event_id        │              │ event_id        │              │ event_id        │   │ event_id     │
│ name            │              │ name            │              │ name            │   │ order_number │
│ price           │              │ capacity        │              │ price           │   │ status       │
│ early_bird_price│              │ price           │              │ max_quantity    │   │ total        │
│ early_bird_deadline│           │ sell_as_whole   │              └─────────────────┘   └──────────────┘
│ max_quantity    │              │ status          │                                            │
│ quantity_sold   │              └─────────────────┘                                            │
└─────────────────┘                      │                                                      │
                                         │                                                      │
                                         ▼                                                      ▼
                                 ┌─────────────────┐                                    ┌──────────────┐
                                 │     seats       │                                    │ order_items  │
                                 ├─────────────────┤                                    ├──────────────┤
                                 │ id              │                                    │ id           │
                                 │ table_id        │                                    │ order_id     │
                                 │ label           │                                    │ item_type    │
                                 │ price           │◄───────────────────────────────────│ seat_id      │
                                 │ status          │                                    │ table_id     │
                                 └─────────────────┘                                    │ ticket_tier_id│
                                         │                                              └──────────────┘
                                         │
                     ┌───────────────────┴───────────────────┐
                     ▼                                       ▼
             ┌─────────────────┐                     ┌─────────────────┐
             │seat_reservations│                     │table_reservations│
             ├─────────────────┤                     ├─────────────────┤
             │ id              │                     │ id              │
             │ seat_id         │                     │ table_id        │
             │ session_token   │                     │ session_token   │
             │ expires_at      │                     │ expires_at      │
             │ order_id        │                     │ order_id        │
             └─────────────────┘                     └─────────────────┘
```

---

## Default Admin Credentials

After running seeders:
- **Email:** admin@eventhub.com
- **Password:** password
- **Role:** super_admin

---

## Default Categories

After running seeders:
| Name | Slug | Color |
|------|------|-------|
| Primaria | primaria | #22c55e |
| Secundaria | secundaria | #3b82f6 |
| Preparatoria | preparatoria | #8b5cf6 |
| General | general | #6b7280 |
