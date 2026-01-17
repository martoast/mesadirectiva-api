# Current Task: E-Ticket Feature

## Overview
Add electronic tickets that are emailed to customers after purchase. Each ticket is a PDF attachment with a QR code that admins can scan for quick check-in.

---

## User Flow
1. Customer purchases tickets via Stripe
2. Stripe webhook confirms payment
3. System generates unique ticket code for each OrderItem
4. System generates PDF ticket for each OrderItem
5. System sends single email to customer with all ticket PDFs attached
6. At event, admin scans QR code on ticket
7. System looks up ticket code, checks in attendee

---

## API Tasks

### 1. Database: Add ticket_code to order_items
- [ ] Create migration `add_ticket_code_to_order_items_table`
  - `ticket_code` VARCHAR(20) UNIQUE NULLABLE
- [ ] Update OrderItem model
  - Add to `$fillable`
  - Add method `generateTicketCode()` - format: `TKT-XXXX-XXXX` (alphanumeric, uppercase)
  - Add static method `findByTicketCode($code)`

### 2. PDF Ticket Generation
- [ ] Install DomPDF package: `composer require barryvdh/laravel-dompdf`
- [ ] Create `app/Services/TicketService.php`
  - `generateTicketPdf(OrderItem $orderItem): string` - returns PDF content
  - `generateAllTicketsForOrder(Order $order): array` - returns array of [filename, content]
- [ ] Create ticket PDF Blade template `resources/views/pdf/ticket.blade.php`
  - Event name, date, time
  - Venue/location info
  - Attendee name
  - Ticket type (tier name OR table/seat info)
  - QR code (encoded ticket_code)
  - Order number
  - Simple, clean design (see design below)

### 3. QR Code Generation
- [ ] Install QR code package: `composer require simplesoftwareio/simple-qrcode`
- [ ] Generate QR in ticket template encoding the ticket_code
- [ ] QR should be large enough to scan easily (~150px)

### 4. Email with Ticket Attachments
- [ ] Create Mailable `app/Mail/OrderTickets.php`
  - Accepts Order with loaded items
  - Attaches PDF for each ticket OrderItem
  - Simple email body with event details
- [ ] Create email Blade template `resources/views/emails/order-tickets.blade.php`
  - Event name & date
  - Order number
  - List of attendee names
  - Brief instructions ("Please have your ticket ready at the door")

### 5. Trigger Email on Payment Success
- [ ] Update `WebhookController::handleStripe()`
  - After marking order as completed:
    1. Generate ticket codes for each OrderItem (where item_type = 'ticket')
    2. Send OrderTickets email (PDFs generated inside Mailable)
- [ ] Consider queue job for async processing (optional)
  - `app/Jobs/SendOrderTicketsJob.php`
  - Dispatch after order completion

### 6. API Endpoint for QR Scan Check-in
- [ ] Add route: `POST /events/{slug}/attendees/scan`
- [ ] Add `AttendeeController::scanCheckIn(Request $request, string $slug)`
  - Accepts `{ ticket_code: "TKT-XXXX-XXXX" }`
  - Validates ticket belongs to this event
  - Checks if already checked in (return error)
  - Checks in the attendee
  - Returns attendee info with success message
- [ ] Add to useAttendees composable on frontend

### 7. Resend Tickets Endpoint (nice to have)
- [ ] Add route: `POST /orders/{orderNumber}/resend-tickets`
- [ ] Regenerates and resends ticket email
- [ ] Useful if customer didn't receive email

---

## Frontend Tasks

### 8. Update useAttendees Composable
- [ ] Add `scanCheckIn(eventSlug, ticketCode)` method
  - Calls `POST /events/{slug}/attendees/scan`
  - Returns attendee data or error

### 9. QR Scanner on Attendees Page
- [ ] Install QR scanner: `yarn add html5-qrcode`
- [ ] Add "Scan Ticket" button in attendees page header
- [ ] Create scanner modal component
  - Opens device camera
  - Shows viewfinder overlay
  - On QR detected: call `scanCheckIn()`
  - Show success with attendee name
  - Show error if invalid/already checked in
  - "Scan Another" for continuous mode
- [ ] Mobile-optimized (primary use case is phone at door)

### 10. Scanner UX
- [ ] Large viewfinder for easy scanning
- [ ] Visual feedback: green flash on success, red on error
- [ ] Show attendee name & ticket type after scan
- [ ] Auto-refresh attendees list after check-in
- [ ] Handle camera permission denied gracefully

---

## PDF Ticket Design

```
┌─────────────────────────────────────────┐
│                                         │
│  ══════════════════════════════════════ │
│                                         │
│  EVENT NAME                             │
│  Saturday, June 15, 2024 · 6:00 PM      │
│                                         │
│  Grand Ballroom, Hotel Marriott         │
│  123 Main Street, Los Angeles, CA       │
│                                         │
│  ─────────────────────────────────────  │
│                                         │
│  ATTENDEE                               │
│  John Doe                               │
│                                         │
│  TICKET TYPE                            │
│  VIP Access                             │
│  (or: Table 5 · Seat 3)                 │
│                                         │
│  ─────────────────────────────────────  │
│                                         │
│         ┌─────────────┐                 │
│         │             │                 │
│         │   QR CODE   │                 │
│         │             │                 │
│         └─────────────┘                 │
│         TKT-A3X9-K2M4                   │
│                                         │
│  ─────────────────────────────────────  │
│                                         │
│  Order: ORD-240615-0042                 │
│  Present this ticket at entry           │
│                                         │
└─────────────────────────────────────────┘
```

Keep it simple - black text on white, clean typography, no heavy branding.

---

## Email Content

**Subject:** Your tickets for [Event Name]

**Body:**
```
Hi [Customer Name],

Your tickets are attached to this email.

EVENT DETAILS
─────────────
[Event Name]
[Date] at [Time]
[Venue Name]
[Address]

YOUR TICKETS
────────────
• John Doe - VIP Access
• Jane Doe - VIP Access
• Bob Smith - General Admission

Order #: ORD-240615-0042

Please have your ticket (printed or on your phone) ready at the door.

See you there!
```

**Attachments:**
- `ticket-john-doe.pdf`
- `ticket-jane-doe.pdf`
- `ticket-bob-smith.pdf`

Filename format: `ticket-{slugified-attendee-name}.pdf`

---

## Implementation Order

1. **Database & Model** - ticket_code field
2. **QR Package** - Install and test QR generation
3. **PDF Package** - Install DomPDF
4. **PDF Template** - Create ticket design
5. **TicketService** - PDF generation logic
6. **Mailable** - Email with attachments
7. **Webhook Update** - Trigger on payment success
8. **Scan Endpoint** - API for QR check-in
9. **Frontend Scanner** - Camera integration
10. **Testing** - End-to-end flow

---

## Testing Checklist
- [ ] Purchase creates ticket codes for all ticket items
- [ ] PDF generates with correct event/attendee info
- [ ] QR code encodes ticket_code correctly
- [ ] QR code is scannable by phone camera
- [ ] Email sends with all PDF attachments
- [ ] Email displays correctly in Gmail/Outlook
- [ ] Scanner opens camera on mobile
- [ ] Scanner reads QR and checks in attendee
- [ ] Scanner shows error for invalid code
- [ ] Scanner shows error for already checked-in
- [ ] Scanner shows error for wrong event
- [ ] Works for both GA and Seated event tickets

---

## Files to Create/Modify

### New Files
- `database/migrations/xxxx_add_ticket_code_to_order_items_table.php`
- `app/Services/TicketService.php`
- `app/Mail/OrderTickets.php`
- `app/Jobs/SendOrderTicketsJob.php` (optional)
- `resources/views/pdf/ticket.blade.php`
- `resources/views/emails/order-tickets.blade.php`

### Modified Files
- `app/Models/OrderItem.php` - Add ticket_code methods
- `app/Http/Controllers/Api/AttendeeController.php` - Add scanCheckIn
- `app/Http/Controllers/Api/WebhookController.php` - Trigger email
- `routes/api.php` - Add scan route
- `composer.json` - Add packages

### Frontend Files
- `composables/useAttendees.js` - Add scanCheckIn method
- `pages/app/admin/events/[slug]/attendees.vue` - Add scanner UI
- `package.json` - Add html5-qrcode

---

## Future Enhancements (Not in scope)
- Apple Wallet / Google Wallet passes
- Ticket transfer to another email
- Web page view of ticket (public URL)
- Ticket void/cancel functionality
- Bulk re-send tickets from admin
- Custom ticket branding per event/group

---

# Previously Completed

## Event Simplification (Done)
- Simplified event fields from 26+ to ~12
- Added Eventbrite-style ticket tiers with sales windows
- Added support for online events
- Added media gallery (images + YouTube)

## Attendees Check-In System (Done)
- Added check-in fields to order_items (checked_in_at, checked_in_by)
- Created AttendeeController with list/checkIn/undoCheckIn
- Created AttendeeResource
- Created frontend attendees page with search/filters
- Added link from event detail page
