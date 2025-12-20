# 📋 PLAN A: Laravel API Backend

## Overview
Build a Laravel REST API that handles authentication, event management, ticket sales, Stripe payments, user permissions, and reporting.

---

## Phase 1: Project Setup & Infrastructure

### 1.1 Docker Configuration

**Files to create/modify:**
- `Dockerfile` (provided – minor adjustments needed)
- `docker-compose.yml` (provided – add Redis for queues)
- `supervisord.conf` (for queue workers)
- `.env.example` with all required variables

**Tasks:**
- Add Redis service to `docker-compose` for queue management
- Configure Supervisor to run queue workers
- Set up MySQL 8.0 with proper character encoding (`utf8mb4`)
- Add health checks for all services

---

### 1.2 Laravel Base Setup

**Commands to run:**
```bash
composer install
php artisan key:generate
php artisan storage:link
Packages to configure:

laravel/sanctum – API token authentication

laravel/fortify – Authentication scaffolding

laravel/cashier – Stripe integration

laravel/socialite

socialiteproviders/google – Google OAuth

league/flysystem-aws-s3-v3 – File uploads to S3

symfony/mailgun-mailer – Transactional emails

Phase 2: Database Schema
2.1 Migrations
Table: users
text
Copy code
id, name, email, password, email_verified_at,
google_id (nullable), avatar (nullable),
role (enum: super_admin, admin, viewer),
is_active (boolean),
remember_token, timestamps
Table: events
text
Copy code
id, slug (unique), name, description,
date, time, location,
price (decimal 10,2), max_tickets, tickets_sold (default 0),
status (enum: draft, live, closed),
hero_title, hero_subtitle, hero_image (S3 path),
about (text),
registration_deadline (datetime, nullable),
requires_approval_after_deadline (boolean, default false),
stripe_product_id (nullable), stripe_price_id (nullable),
created_by (foreign: users),
timestamps, soft_deletes
Table: event_items
text
Copy code
id, event_id (foreign),
name, description, price (decimal 10,2),
max_quantity (nullable), quantity_sold (default 0),
is_active (boolean),
stripe_price_id (nullable),
timestamps
Table: event_user
text
Copy code
id, event_id (foreign), user_id (foreign),
permission (enum: view, edit, manage),
timestamps
Table: orders
text
Copy code
id, order_number (unique),
event_id (foreign),
customer_name, customer_email, customer_phone (nullable),
status (enum: pending, completed, failed, refunded),
subtotal, total (decimal 10,2),
stripe_checkout_session_id,
stripe_payment_intent_id (nullable),
paid_at (datetime, nullable),
metadata (json, nullable),
timestamps
Table: order_items
text
Copy code
id, order_id (foreign),
item_type (enum: ticket, extra_item),
item_id (nullable),
item_name, quantity, unit_price, total_price,
timestamps
Table: activity_logs
text
Copy code
id, user_id (foreign, nullable),
action, model_type, model_id,
old_values (json), new_values (json),
ip_address, user_agent,
timestamps
2.2 Seeders
AdminUserSeeder – Create default super admin

DemoEventsSeeder – Sample events (optional)

Phase 3: Authentication System
3.1 Fortify Configuration
File: config/fortify.php

Enabled features:

Login

Password reset

Email verification
(Public registration disabled)

3.2 Sanctum API Tokens
File: config/sanctum.php

Stateful domains for SPA

Token expiration (24h)

Token abilities:

events:read

events:write

orders:read

reports:read

3.3 Google OAuth
File: config/services.php

Flow:

Redirect to /api/auth/google/redirect

Authenticate with Google

Callback creates/updates user

Issue Sanctum token

Redirect to frontend

3.4 Auth Controllers
AuthController

POST /api/auth/login

POST /api/auth/logout

GET /api/auth/user

POST /api/auth/forgot-password

POST /api/auth/reset-password

GoogleAuthController

GET /api/auth/google/redirect

GET /api/auth/google/callback

Phase 4: Event Management API
4.1 Event CRUD
EventController

Method	Endpoint	Description
GET	/api/events	List events
GET	/api/events/{slug}	Get event
POST	/api/events	Create
PUT	/api/events/{slug}	Update
DELETE	/api/events/{slug}	Soft delete
POST	/api/events/{slug}/publish	Publish
POST	/api/events/{slug}/close	Close
POST	/api/events/{slug}/duplicate	Clone

Validation (StoreEventRequest):

php
Copy code
'name' => 'required|string|max:255',
'date' => 'required|date|after:today',
'time' => 'required|date_format:H:i',
'location' => 'required|string|max:255',
'price' => 'required|numeric|min:0',
'max_tickets' => 'required|integer|min:1',
'hero_title' => 'required|string|max:255',
'hero_subtitle' => 'required|string|max:500',
'about' => 'required|string',
'registration_deadline' => 'nullable|date|after:today',
'status' => 'in:draft,live'
Business Rules:

Auto-generate slug

Stripe product/price on publish

Prevent deletion if orders exist

Enforce registration deadline

4.2 Event Items
EventItemController

GET /api/events/{slug}/items

POST /api/events/{slug}/items

PUT /api/events/{slug}/items/{id}

DELETE /api/events/{slug}/items/{id}

4.3 Image Upload
EventImageController

POST /api/events/{slug}/hero-image

Rules:

JPG / PNG / WEBP

Max 1920×1080

Upload to S3

Replace old image

Phase 5: Public Event API
PublicEventController

GET /api/public/events

GET /api/public/events/{slug}

GET /api/public/events/{slug}/availability

Sample Response:

json
Copy code
{
  "slug": "summer-music-fest-2025",
  "name": "Summer Music Fest 2025",
  "date": "2025-07-15",
  "time": "18:00",
  "location": "Central Park, NYC",
  "price": 75.00,
  "tickets_available": 266,
  "max_tickets": 500,
  "extra_items": [
    { "id": 1, "name": "Extra Guest", "price": 50.00 }
  ]
}
Phase 6: Stripe Checkout
6.1 Checkout Flow
CheckoutController

POST /api/checkout/create-session

GET /api/checkout/success

POST /api/webhooks/stripe

Create Session Payload:

json
Copy code
{
  "event_slug": "summer-music-fest-2025",
  "customer_name": "John Doe",
  "customer_email": "john@example.com",
  "tickets": 2
}
6.2 Webhooks
Handled events:

checkout.session.completed

payment_intent.payment_failed

charge.refunded

6.3 Stripe Product Creation
php
Copy code
$product = $stripe->products->create([
    'name' => $event->name,
    'description' => $event->about
]);

$price = $stripe->prices->create([
    'product' => $product->id,
    'unit_amount' => $event->price * 100,
    'currency' => 'usd'
]);
Phase 7: Users & Permissions
Policies
php
Copy code
public function update(User $user, Event $event): bool
{
    if ($user->role === 'super_admin') return true;
    return in_array(
        $user->events()->find($event->id)?->pivot->permission,
        ['edit', 'manage']
    );
}
Phase 8: Reporting
Dashboard
/api/dashboard/stats

/api/dashboard/events/{slug}/stats

Reports
/api/reports/sales

/api/reports/orders

Excel export via maatwebsite/excel

Phase 9: Email & Queues
Order confirmation

Password reset

Optional event reminder

Redis + Supervisor

Phase 10: API Documentation
Use darkaonline/l5-swagger

API File Structure
text
Copy code
app/
├── Http/
├── Models/
├── Policies/
├── Services/
├── Mail/
├── Exports/
└── Observers/