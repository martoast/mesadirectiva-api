# MesaDirectiva API - LLM Developer Guide

> **Quick Context**: Laravel REST API for event ticketing. Supports General Admission (ticket tiers with sales windows) and Seated events (tables/seats with reservations). Stripe payments, role-based access with group permissions.

---

## Tech Stack

- **Framework**: Laravel 12 (PHP 8.4)
- **Database**: MySQL 8.4 (Eloquent ORM)
- **Auth**: Laravel Sanctum (token-based)
- **Payments**: Stripe Checkout + Webhooks
- **Storage**: AWS S3 (DigitalOcean Spaces)
- **Exports**: Maatwebsite Excel

---

## Directory Structure

```
mesadirectiva-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/      # API endpoints
│   │   │   ├── AuthController.php
│   │   │   ├── EventController.php
│   │   │   ├── TicketTierController.php
│   │   │   ├── TableController.php
│   │   │   ├── SeatController.php
│   │   │   ├── AttendeeController.php
│   │   │   ├── CheckoutController.php
│   │   │   ├── PublicEventController.php
│   │   │   ├── PublicSeatingController.php
│   │   │   ├── GroupController.php
│   │   │   ├── UserController.php
│   │   │   ├── ReportController.php
│   │   │   └── WebhookController.php
│   │   ├── Requests/             # Form validation
│   │   └── Resources/            # JSON transformers
│   │       ├── EventResource.php
│   │       ├── TicketTierResource.php
│   │       ├── TableResource.php
│   │       ├── SeatResource.php
│   │       ├── AttendeeResource.php
│   │       ├── OrderResource.php
│   │       └── ...
│   ├── Models/                   # Eloquent models
│   │   ├── User.php
│   │   ├── Event.php
│   │   ├── Group.php
│   │   ├── TicketTier.php
│   │   ├── Table.php
│   │   ├── Seat.php
│   │   ├── Order.php
│   │   ├── OrderItem.php
│   │   ├── TableReservation.php
│   │   └── SeatReservation.php
│   ├── Policies/                 # Authorization
│   │   ├── EventPolicy.php
│   │   └── GroupPolicy.php
│   └── Services/                 # Business logic
│       ├── StripeService.php
│       ├── ReservationService.php
│       └── ImageService.php
├── database/migrations/          # Schema
├── routes/api.php                # All API routes
└── config/                       # Configuration
```

---

## Key Models & Relationships

### User
```php
// Roles: super_admin, admin, viewer
$user->groups           // BelongsToMany (with permission pivot)
$user->createdEvents    // HasMany
$user->isSuperAdmin()   // Check role
$user->hasGroupPermission($group, 'edit')  // Check permission
```

### Event
```php
$event->group           // BelongsTo
$event->ticketTiers     // HasMany (GA events)
$event->tables          // HasMany (Seated events)
$event->orders          // HasMany
$event->items           // HasMany (add-ons)

// Scopes
Event::live()           // status = 'live'
Event::accessibleBy($user)  // User's groups
```

### TicketTier (General Admission)
```php
$tier->event            // BelongsTo
$tier->orderItems       // HasMany

// Methods
$tier->isAvailable()    // Check if on sale
$tier->isSoldOut()
$tier->getSalesStatus() // 'on_sale', 'scheduled', 'ended', 'sold_out'
```

### Table & Seat (Seated Events)
```php
$table->event           // BelongsTo
$table->seats           // HasMany
$table->reservation     // HasOne (TableReservation)
$table->isAvailable()
$table->markAsReserved($token, $minutes)
$table->markAsSold($orderId)

$seat->table            // BelongsTo
$seat->reservation      // HasOne (SeatReservation)
```

### Order & OrderItem
```php
$order->event           // BelongsTo
$order->items           // HasMany (OrderItem)

$orderItem->order       // BelongsTo
$orderItem->ticketTier  // BelongsTo (nullable)
$orderItem->table       // BelongsTo (nullable)
$orderItem->seat        // BelongsTo (nullable)
$orderItem->checkedInBy // BelongsTo User (nullable)

// Check-in methods
$orderItem->isCheckedIn()
$orderItem->checkIn($userId)
$orderItem->undoCheckIn()
```

---

## API Routes Overview

### Public (No Auth)
```
GET    /public/events                    # List live public events
GET    /public/events/{slug}             # Event details
GET    /public/events/{slug}/availability
GET    /public/events/{slug}/ticket-tiers
GET    /public/events/{slug}/tables
GET    /public/events/{slug}/tables/{id}/seats
POST   /public/events/{slug}/reserve     # Create reservation
DELETE /public/events/{slug}/reserve     # Release reservation
POST   /checkout/create-session          # Stripe checkout
POST   /webhooks/stripe                  # Stripe webhooks
```

### Auth Required
```
POST   /auth/login
POST   /auth/logout
GET    /auth/user

# Events (filtered by user's groups)
GET    /events
POST   /events
GET    /events/{slug}
PUT    /events/{slug}
DELETE /events/{slug}
POST   /events/{slug}/publish
POST   /events/{slug}/close
POST   /events/{slug}/image              # Upload hero image
POST   /events/{slug}/media              # Add gallery media

# Ticket Tiers
GET    /events/{slug}/ticket-tiers
POST   /events/{slug}/ticket-tiers
PUT    /events/{slug}/ticket-tiers/{id}
DELETE /events/{slug}/ticket-tiers/{id}

# Tables & Seats
GET    /events/{slug}/tables
POST   /events/{slug}/tables
POST   /events/{slug}/tables/bulk        # Bulk create
PUT    /events/{slug}/tables/{id}
DELETE /events/{slug}/tables/{id}
POST   /events/{slug}/tables/{id}/seats/bulk

# Attendees & Check-in
GET    /events/{slug}/attendees          # List with search/filters
POST   /events/{slug}/attendees/{id}/check-in
POST   /events/{slug}/attendees/{id}/undo-check-in

# Orders
GET    /events/{slug}/orders
GET    /orders/{orderNumber}

# Super Admin Only
GET    /users
POST   /users
PUT    /users/{id}
DELETE /users/{id}
GET    /groups
POST   /groups
...
```

---

## Key Patterns

### 1. Controller Pattern
```php
class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Get events user can access
        $events = Event::accessibleBy($request->user())
            ->with(['group', 'ticketTiers'])
            ->paginate(20);

        return response()->json([
            'events' => EventResource::collection($events),
            'meta' => [...pagination...]
        ]);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        // Validation happens in StoreEventRequest
        $event = Event::create($request->validated());
        return response()->json(['event' => new EventResource($event)], 201);
    }
}
```

### 2. Resource Pattern
```php
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            // Conditional relationships
            'group' => $this->whenLoaded('group', fn() => new GroupResource($this->group)),
            // Computed fields
            'can_purchase' => $this->canPurchase(),
        ];
    }
}
```

### 3. Policy Pattern
```php
class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin()) return true;
        return $user->hasGroupPermission($event->group, 'view');
    }

    public function update(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin()) return true;
        return $user->hasGroupPermission($event->group, 'edit');
    }
}

// In controller:
$this->authorize('update', $event);
```

### 4. Form Request Pattern
```php
class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Event::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'group_id' => 'required|exists:groups,id',
            'starts_at' => 'required|date',
            // ...
        ];
    }
}
```

### 5. Service Pattern
```php
// app/Services/ReservationService.php
class ReservationService
{
    public function reserve(Event $event, array $items, int $minutes = 15): string
    {
        $token = Str::uuid();

        DB::transaction(function () use ($event, $items, $token, $minutes) {
            foreach ($items as $item) {
                if ($item['type'] === 'table') {
                    $table = Table::lockForUpdate()->find($item['id']);
                    $table->markAsReserved($token, $minutes);
                }
                // ...
            }
        });

        return $token;
    }
}
```

---

## Authentication & Authorization

### Auth Flow
1. `POST /auth/login` with email/password
2. Returns `{ token: "...", user: {...} }`
3. Include `Authorization: Bearer {token}` on requests
4. Token validated via Sanctum middleware

### Role Hierarchy
| Role | Access |
|------|--------|
| `super_admin` | Everything |
| `admin` | Events in assigned groups |
| `viewer` | Read-only on assigned groups |

### Group Permissions
| Permission | Can Do |
|------------|--------|
| `view` | Read events |
| `edit` | Create/update events |
| `manage` | Delete events, full control |

---

## Event Types

### General Admission (`seating_type: 'general_admission'`)
- Uses `TicketTier` model
- Each tier has: name, price, quantity, sales_start, sales_end
- Eventbrite-style sales windows
- `available_ticket_tiers` scope filters to on-sale tiers

### Seated (`seating_type: 'seated'`)
- Uses `Table` and `Seat` models
- Tables can be `sell_as_whole: true` (whole table) or `false` (individual seats)
- Reservations hold items during checkout (default 15 min)
- `ReservationService` handles locking and expiry

---

## Stripe Integration

### Checkout Flow
1. Frontend calls `POST /checkout/create-session`
2. `CheckoutController` creates Stripe Session with line items
3. User redirected to Stripe
4. On success, Stripe posts to `/webhooks/stripe`
5. `WebhookController` marks order as completed

### Key Service Methods
```php
// app/Services/StripeService.php
$stripeService->createEventProduct($event);     // Create Stripe Product
$stripeService->createItemPrice($item, $event); // Create Stripe Price
```

---

## Common Tasks

### Add a New Endpoint
1. Add route in `routes/api.php`
2. Create/update controller method
3. Create Form Request for validation (if needed)
4. Create/update Resource for response
5. Add policy method if authorization needed

### Add a New Model Field
1. Create migration: `php artisan make:migration add_field_to_table`
2. Add to model's `$fillable` array
3. Add to model's `$casts` if needed
4. Update relevant Resource classes
5. Update Form Request validation rules

### Add a New Relationship
1. Add method to model
2. Eager load in controller: `->with(['newRelation'])`
3. Add to Resource: `$this->whenLoaded('newRelation', ...)`

### Create a New Service
1. Create class in `app/Services/`
2. Inject in controller constructor or use `app(ServiceClass::class)`

---

## Database Schema Highlights

### Key Tables
- `users` - Auth, roles, OAuth fields
- `groups` - Organization grouping
- `group_user` - Pivot with permission column
- `events` - Core event data, soft deletes
- `ticket_tiers` - GA pricing/inventory
- `tables` - Seated event tables
- `seats` - Individual seats
- `orders` - Customer orders
- `order_items` - Line items with check-in fields
- `table_reservations` / `seat_reservations` - Temp holds

### Check-in Fields (order_items)
```sql
checked_in_at  TIMESTAMP NULL
checked_in_by  BIGINT UNSIGNED NULL  -- FK to users
```

---

## Environment Variables

```bash
APP_URL=http://localhost:8001
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=mesadirectiva

STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=nyc3
AWS_BUCKET=your-bucket
AWS_ENDPOINT=https://nyc3.digitaloceanspaces.com
```

---

## Running Locally

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --port=8001
```

---

## Important Files to Read First

| File | Purpose |
|------|---------|
| `routes/api.php` | All API routes |
| `app/Models/Event.php` | Core model with scopes |
| `app/Models/User.php` | Auth & permissions |
| `app/Http/Controllers/Api/EventController.php` | Reference controller |
| `app/Http/Resources/EventResource.php` | Reference resource |
| `app/Policies/EventPolicy.php` | Authorization logic |
| `app/Services/ReservationService.php` | Reservation logic |
| `app/Services/StripeService.php` | Payment logic |

---

## Notes for LLMs

- **Always use policies** for authorization: `$this->authorize('action', $model)`
- **Always use resources** for JSON responses (consistent format)
- **Always use form requests** for validation (keeps controllers clean)
- **Eager load relationships** to avoid N+1: `->with(['relation'])`
- **Use scopes** for reusable queries: `Event::live()->accessibleBy($user)`
- **Use transactions** for multi-step operations: `DB::transaction(fn() => ...)`
- **Soft deletes on Event** - use `$event->delete()`, not `forceDelete()`
- **Check existing patterns** before creating new ones - consistency is key
