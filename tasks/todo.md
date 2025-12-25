# Seating-Based Events Feature - Implementation Plan

## Overview

Add support for seating-based events (tables + seats) AND enhance general admission with ticket tiers and early bird pricing. Events use ONE ticketing model - they are mutually exclusive:

| Event Type | How It Works |
|------------|--------------|
| **General Admission** | Ticket tiers (e.g., General, VIP, Premium) with early bird pricing. Quantity-based purchasing. |
| **Seated** | Tables and seats only. Customers select specific tables or seats to purchase. |

This ensures no conflict between the two models.

---

## Phase 1: Database Schema

### 1.1 New Tables (General Admission)

#### `ticket_tiers`
Ticket tiers for general admission events with early bird pricing.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| event_id | bigint FK | Reference to events table |
| name | string | Tier name (e.g., "General", "VIP", "Premium") |
| description | text nullable | What's included in this tier |
| price | decimal(10,2) | Regular ticket price |
| early_bird_price | decimal(10,2) nullable | Discounted early bird price |
| early_bird_deadline | timestamp nullable | When early bird pricing ends |
| max_quantity | int nullable | Max tickets for this tier (null = unlimited) |
| quantity_sold | int default 0 | Tickets sold for this tier |
| sort_order | int default 0 | Display ordering |
| is_active | boolean default true | Soft disable |
| created_at | timestamp | |
| updated_at | timestamp | |

**Pricing Logic:** If `early_bird_deadline` is set and `now() < early_bird_deadline`, use `early_bird_price`. Otherwise use `price`.

### 1.2 New Tables (Seated Events)

#### `tables`
Tables belonging to an event. Can be sold as a whole or have individual seats.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| event_id | bigint FK | Reference to events table |
| name | string | Table identifier (e.g., "Table 1", "A1") |
| capacity | int | Number of seats at this table |
| price | decimal(10,2) | Price for whole table purchase |
| sell_as_whole | boolean default true | If true, table must be purchased as a unit |
| status | enum default 'available' | 'available', 'reserved', 'sold' |
| position_x | int nullable | X coordinate for seating chart UI |
| position_y | int nullable | Y coordinate for seating chart UI |
| is_active | boolean default true | Soft disable |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `seats`
Individual seats belonging to a table. Only used when `table.sell_as_whole = false`.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| table_id | bigint FK | Reference to tables |
| label | string | Seat identifier (e.g., "A1", "Seat 5") |
| status | enum | 'available', 'reserved', 'sold' |
| price | decimal(10,2) | Price for this seat |
| position_x | int nullable | X coordinate for seating chart UI |
| position_y | int nullable | Y coordinate for seating chart UI |
| is_active | boolean default true | Soft disable |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `seat_reservations`
Temporary holds during checkout process.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| seat_id | bigint FK | Reference to seats |
| session_token | string | Unique checkout session identifier |
| order_id | bigint FK nullable | Reference to orders (set when order created) |
| expires_at | timestamp | When this reservation expires |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `table_reservations`
Temporary holds for whole-table purchases during checkout.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| table_id | bigint FK | Reference to tables |
| session_token | string | Unique checkout session identifier |
| order_id | bigint FK nullable | Reference to orders (set when order created) |
| expires_at | timestamp | When this reservation expires |
| created_at | timestamp | |
| updated_at | timestamp | |

### 1.2 Modify Existing Tables

#### `events` table additions
| Column | Type | Description |
|--------|------|-------------|
| seating_type | enum default 'general_admission' | 'general_admission' or 'seated' |
| reservation_minutes | int default 15 | How long seats/tables are held during checkout |

#### `order_items` table additions
| Column | Type | Description |
|--------|------|-------------|
| ticket_tier_id | bigint FK nullable | Reference to ticket_tiers (for GA events) |
| seat_id | bigint FK nullable | Reference to seats (for individual seat purchases) |
| table_id | bigint FK nullable | Reference to tables (for whole-table purchases) |

---

## Phase 2: Models & Relationships

### 2.1 New Models (General Admission)

- **TicketTier** - belongsTo Event, hasMany OrderItems, has `getCurrentPrice()` method

### 2.2 New Models (Seated Events)

- **Table** - belongsTo Event, hasMany Seats, hasOne TableReservation
- **Seat** - belongsTo Table, hasOne SeatReservation, hasMany OrderItems
- **SeatReservation** - belongsTo Seat, belongsTo Order (nullable)
- **TableReservation** - belongsTo Table, belongsTo Order (nullable)

### 2.3 Update Existing Models

- **Event** - hasMany TicketTiers, hasMany Tables, add `isSeated()` helper
- **OrderItem** - belongsTo TicketTier (nullable), belongsTo Seat (nullable), belongsTo Table (nullable)

---

## Phase 3: API Endpoints

### 3.1 Admin Endpoints (Protected)

#### Ticket Tiers (for General Admission events)
```
GET    /events/{slug}/ticket-tiers           List all tiers
POST   /events/{slug}/ticket-tiers           Create tier
GET    /events/{slug}/ticket-tiers/{id}      Get tier details
PUT    /events/{slug}/ticket-tiers/{id}      Update tier
DELETE /events/{slug}/ticket-tiers/{id}      Delete tier
```

#### Tables (for Seated events)
```
GET    /events/{slug}/tables                 List all tables
POST   /events/{slug}/tables                 Create table
GET    /events/{slug}/tables/{id}            Get table with seats
PUT    /events/{slug}/tables/{id}            Update table
DELETE /events/{slug}/tables/{id}            Delete table
POST   /events/{slug}/tables/bulk            Bulk create tables
```

#### Seats (for tables with sell_as_whole = false)
```
GET    /events/{slug}/tables/{id}/seats      List seats for a table
POST   /events/{slug}/tables/{id}/seats      Create seat
PUT    /events/{slug}/seats/{id}             Update seat
DELETE /events/{slug}/seats/{id}             Delete seat
POST   /events/{slug}/tables/{id}/seats/bulk Bulk create seats for table
```

### 3.2 Public Endpoints

#### Ticket Tiers (for General Admission events)
```
GET    /public/events/{slug}/ticket-tiers    Get available tiers with current prices
```

#### Tables & Seats (for Seated events)
```
GET    /public/events/{slug}/tables          Get all tables with availability
GET    /public/events/{slug}/tables/{id}/seats  Get available seats for a table
```

#### Reservations (During Checkout)
```
POST   /public/events/{slug}/reserve         Reserve tables or seats
DELETE /public/events/{slug}/reserve         Release reservation
```

Reserve request body:
```json
{
  "tables": [1, 2],        // For whole-table purchases
  "seats": [5, 6, 7]       // For individual seat purchases
}
```

### 3.3 Modify Existing Endpoints

#### `POST /checkout/create-session`

**For General Admission events** (updated for tiers):
```json
{
  "event_slug": "concert-2024",
  "customer_name": "Jane Smith",
  "customer_email": "jane@example.com",
  "tiers": [
    { "tier_id": 1, "quantity": 2 },
    { "tier_id": 2, "quantity": 1 }
  ],
  "extra_items": [...]
}
```

**For Seated events**:
```json
{
  "event_slug": "gala-2024",
  "customer_name": "Jane Smith",
  "customer_email": "jane@example.com",
  "tables": [1],                // Whole-table purchases
  "seats": [5, 6, 7],           // Individual seat purchases
  "reservation_token": "abc123",
  "extra_items": [...]
}
```

Note: `tiers` field is for GA events. `tables`/`seats` fields are for seated events. Using the wrong fields returns a validation error.

#### `GET /public/events/{slug}/availability`

**For General Admission** (updated):
```json
{
  "can_purchase": true,
  "seating_type": "general_admission",
  "tiers": [
    {
      "id": 1,
      "name": "General",
      "price": 100.00,
      "current_price": 80.00,
      "is_early_bird": true,
      "early_bird_deadline": "2024-06-01T23:59:59Z",
      "available": 150
    },
    {
      "id": 2,
      "name": "VIP",
      "price": 200.00,
      "current_price": 200.00,
      "is_early_bird": false,
      "available": 50
    }
  ],
  "items": [...]
}
```

**For Seated events** (new):
```json
{
  "can_purchase": true,
  "seating_type": "seated",
  "tables_available": 10,
  "tables_total": 15,
  "seats_available": 25,
  "seats_total": 40
}
```

---

## Phase 4: Business Logic

### 4.1 Reservation Service

**Reserve(table_ids, seat_ids, session_token):**
1. Validate all items belong to same event
2. For seats: verify their table has `sell_as_whole = false`
3. Acquire database lock on tables/seats
4. Check all items are 'available'
5. Create reservation records with expiry timestamp
6. Update status to 'reserved'
7. Return reservation token

**Release(reservation_token):**
1. Find reservations by token
2. Delete reservation records
3. Update status back to 'available'

### 4.2 Expire Reservations (Scheduled Job)

**ExpireReservations():**
1. Find all expired seat reservations → release seats
2. Find all expired table reservations → release tables
3. Delete expired reservation records

### 4.3 Checkout Modifications

**For Seated Events:**
1. Validate reservation token matches the tables/seats being purchased
2. Verify reservation hasn't expired
3. Create order with table_id or seat_id in order_items
4. On payment success:
   - For tables: Update table status to 'sold'
   - For seats: Update seat status to 'sold'
   - Delete reservations
5. On payment failure: Release reservations

### 4.4 Validation Rules

**Event Type Rules:**
- Cannot change seating_type after event has orders
- Seated events ignore `max_tickets` and `price` fields (capacity comes from tables/seats)
- General admission events ignore tables/seats entirely

**Seated Event Rules:**
- Cannot publish without at least one table
- Cannot delete table with sold status
- Cannot change status to 'available' if 'sold' (unless refunded)
- Cannot purchase individual seats from a table where `sell_as_whole = true`
- Tables with `sell_as_whole = false` must have seats defined

---

## Phase 5: Stripe Integration

### 5.1 Dynamic Line Items

For seated events, Stripe Checkout line items show:
- **Whole tables:** "Table 5 (8 seats)" with table price
- **Individual seats:** "Table 3 - Seat A1" with seat price

### 5.2 Metadata

Store in Stripe session metadata:
```json
{
  "seat_ids": "1,2,3",
  "table_ids": "5",
  "reservation_token": "abc123"
}
```

---

## Phase 6: Scheduled Tasks

### 6.1 Expire Seat Reservations

Add Laravel scheduled command to run every minute:
```
php artisan seats:expire-reservations
```

This releases seats where `expires_at < now()` and marks them available again.

---

## Phase 7: Database Migrations (Execution Order)

1. `add_seating_type_to_events_table` - Add seating_type, reservation_minutes
2. `create_ticket_tiers_table` - For GA events with early bird pricing
3. `create_tables_table` - For seated events
4. `create_seats_table` - For seated events
5. `create_seat_reservations_table`
6. `create_table_reservations_table`
7. `add_seating_fields_to_order_items_table` - Add ticket_tier_id, seat_id, table_id

---

## Phase 8: API Resources

### 8.1 New Resources (General Admission)

- **TicketTierResource** - id, name, description, price, early_bird_price, early_bird_deadline, current_price, is_early_bird, available, max_quantity

### 8.2 New Resources (Seated Events)

- **TableResource** - id, name, capacity, price, sell_as_whole, status, position, seats_count, seats_available
- **SeatResource** - id, label, status, price, position, table_id
- **ReservationResource** - token, expires_at, tables, seats

### 8.3 Update Existing Resources

- **EventResource** - Add seating_type, reservation_minutes, tiers (for GA), tables_count (for seated)
- **OrderItemResource** - Add tier/seat/table info when applicable

---

## Phase 9: Testing

### 9.1 Unit Tests
- Seat reservation logic
- Expiration handling
- Price calculation (override vs area price)
- Status transitions

### 9.2 Feature Tests
- Reserve seats API
- Checkout with seats
- Concurrent reservation handling (race conditions)
- Webhook processing with seat assignment

---

## Implementation Checklist

### Database
- [x] Migration: add seating_type to events
- [x] Migration: create ticket_tiers table
- [x] Migration: create tables table
- [x] Migration: create seats table
- [x] Migration: create seat_reservations table
- [x] Migration: create table_reservations table
- [x] Migration: add ticket_tier_id, seat_id, table_id to order_items

### Models
- [x] TicketTier model with getCurrentPrice() method
- [x] Table model with relationships
- [x] Seat model with relationships
- [x] SeatReservation model
- [x] TableReservation model
- [x] Update Event model (hasMany tiers, tables)
- [x] Update OrderItem model

### Services
- [x] ReservationService (for seated events)

### Controllers
- [x] TicketTierController (CRUD)
- [x] TableController (CRUD + bulk)
- [x] SeatController (CRUD + bulk)
- [x] PublicSeatingController (list available, reserve, release)
- [x] Update CheckoutController
- [x] Update PublicEventController

### API Resources
- [x] TicketTierResource
- [x] TableResource
- [x] SeatResource
- [x] ReservationResource
- [x] Update EventResource
- [x] Update OrderItemResource

### Requests (Validation)
- [x] StoreTicketTierRequest
- [x] UpdateTicketTierRequest
- [x] StoreTableRequest
- [x] UpdateTableRequest
- [x] StoreSeatRequest
- [x] UpdateSeatRequest
- [x] ReserveRequest
- [x] Update CreateCheckoutSessionRequest

### Routes
- [x] Admin routes for ticket tiers
- [x] Admin routes for tables/seats
- [x] Public routes for tiers, tables, seats, reservations

### Scheduled Commands
- [x] ExpireReservationsCommand
- [x] Register in bootstrap/app.php (Laravel 12)

### Stripe
- [x] Update checkout session for tiers (with early bird pricing)
- [x] Update checkout session for tables/seats
- [x] Update webhook handler

### Tests
- [ ] TicketTier CRUD tests
- [ ] Early bird pricing logic tests
- [ ] Table CRUD tests
- [ ] Seat CRUD tests
- [ ] Reservation flow tests
- [ ] Checkout with tiers tests
- [ ] Checkout with seating tests
- [ ] Concurrent reservation tests

---

## Notes

### Event Types Are Mutually Exclusive

```
┌─────────────────────────────────────────────────────────────────┐
│                         EVENT                                   │
│                    seating_type = ?                             │
└─────────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┴───────────────┐
              ▼                               ▼
┌─────────────────────────┐     ┌─────────────────────────┐
│   GENERAL ADMISSION     │     │        SEATED           │
├─────────────────────────┤     ├─────────────────────────┤
│ • ticket_tiers          │     │ • tables (→ seats)      │
│   - General, VIP, etc.  │     │ • reservations          │
│   - early bird pricing  │     │ • NO ticket tiers       │
│ • quantity-based        │     │ • selection-based       │
└─────────────────────────┘     └─────────────────────────┘
```

### Backwards Compatibility
- Existing events default to `seating_type = 'general_admission'`
- Existing events using single `price` field will need migration to ticket_tiers
- New seating logic only activates when `seating_type = 'seated'`

### Early Bird Pricing Logic
```php
// TicketTier model
public function getCurrentPrice(): float
{
    if ($this->early_bird_price
        && $this->early_bird_deadline
        && now()->lt($this->early_bird_deadline)) {
        return $this->early_bird_price;
    }
    return $this->price;
}

public function isEarlyBird(): bool
{
    return $this->early_bird_deadline && now()->lt($this->early_bird_deadline);
}
```

### Table Purchase Modes (for Seated events)

| sell_as_whole | Behavior |
|---------------|----------|
| `true` (default) | Entire table purchased as one unit at table price |
| `false` | Individual seats can be purchased, each seat has its own price |

### Future Considerations (Out of Scope)
- Visual seating chart editor (frontend)
- Seat categories (wheelchair accessible, etc.)
- Waitlist for sold-out tables/seats
