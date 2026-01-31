# Current Task: Email & Ticket CMS (Per-Event Customization)

## Overview
Allow admins to customize the confirmation email and PDF ticket content per event. Admins can set custom text for the email subject, greeting, intro, instructions, footer, and ticket footer from the event settings. All fields use sensible Spanish defaults if left empty.

---

## Plan

### 1. Migration: Add `email_settings` JSON column to events
- Create migration `add_email_settings_to_events_table`
- Add `email_settings` JSON NULLABLE column to `events` table
- Structure:
  ```json
  {
    "email_subject": "Tus boletos para {event_name}",
    "email_greeting": "Hola {customer_name},",
    "email_intro": "Tus boletos están adjuntos a este correo.",
    "email_instructions": "Por favor ten tu boleto (impreso o en tu teléfono) listo en la entrada.",
    "email_footer": "¡Te esperamos!",
    "ticket_footer": "Presenta este boleto en la entrada"
  }
  ```
- All fields optional — templates use hardcoded Spanish defaults when empty/null

### 2. Update Event model
- Add `email_settings` to `$fillable`
- Add `email_settings` to `$casts` as `array`
- Add helper method `getEmailSetting(string $key, ?string $default = null): ?string`
  - Returns the value from `email_settings[$key]` or the given default
  - Supports placeholder replacement: `{event_name}`, `{customer_name}`

### 3. Update validation (UpdateEventRequest)
- Add `email_settings` as optional `array`
- Add `email_settings.email_subject` as optional `string|max:255`
- Add `email_settings.email_greeting` as optional `string|max:255`
- Add `email_settings.email_intro` as optional `string|max:500`
- Add `email_settings.email_instructions` as optional `string|max:500`
- Add `email_settings.email_footer` as optional `string|max:255`
- Add `email_settings.ticket_footer` as optional `string|max:255`

### 4. Update email template (`resources/views/emails/order-tickets.blade.php`)
- Replace hardcoded text with variables that fall back to current defaults:
  - Greeting: `$emailGreeting ?? 'Hola {customer_name},'`
  - Intro: `$emailIntro ?? 'Tus boletos están adjuntos a este correo.'`
  - Instructions: `$emailInstructions ?? 'Por favor ten tu boleto...'`
  - Footer: `$emailFooter ?? '¡Te esperamos!'`

### 5. Update `OrderTickets` Mailable
- Read `email_settings` from `$this->order->event`
- Pass custom text to the view, replacing `{event_name}` and `{customer_name}` placeholders
- Use custom subject if provided, otherwise default

### 6. Update PDF ticket template (`resources/views/pdf/ticket.blade.php`)
- Replace hardcoded footer text with custom `ticket_footer` (with fallback)

### 7. Update `TicketService`
- Pass `email_settings` data to the PDF view

---

## Defaults (Spanish)
| Field | Default Value |
|-------|--------------|
| `email_subject` | `Tus boletos para {event_name}` |
| `email_greeting` | `Hola {customer_name},` |
| `email_intro` | `Tus boletos están adjuntos a este correo.` |
| `email_instructions` | `Por favor ten tu boleto (impreso o en tu teléfono) listo en la entrada.` |
| `email_footer` | `¡Te esperamos!` |
| `ticket_footer` | `Presenta este boleto en la entrada` |

## Supported Placeholders
- `{event_name}` — replaced with the event's name
- `{customer_name}` — replaced with the buyer's name

---

## Files to Create
- `database/migrations/xxxx_add_email_settings_to_events_table.php`

## Files to Modify
- `app/Models/Event.php` — add field + helper
- `app/Http/Requests/UpdateEventRequest.php` — validation
- `app/Mail/OrderTickets.php` — use custom settings
- `resources/views/emails/order-tickets.blade.php` — dynamic text
- `resources/views/pdf/ticket.blade.php` — dynamic footer
- `app/Services/TicketService.php` — pass settings to PDF view

---

# Previously Completed

## E-Ticket Feature (Done)
- PDF ticket generation with QR codes
- Email with PDF attachments on purchase
- QR scan check-in endpoint
- Ticket code generation (TKT-XXXX-XXXX)

## Event Simplification (Done)
- Simplified event fields
- Eventbrite-style ticket tiers with sales windows
- Online events support
- Media gallery (images + YouTube)

## Attendees Check-In System (Done)
- Check-in fields on order_items
- AttendeeController with list/checkIn/undoCheckIn
- Frontend attendees page with search/filters
