# Roadmap — API (updated 2026-09-05)

Frontend counterpart: `app/tasks/todo.md`.

The five 2026-07 workstreams (multi-Stripe, parcialidades, checkout student fields,
two-level reports, Tijuana timezone) all shipped and are live in production.
See "Shipped" at the bottom and `git log` for detail — nothing left to do there.

---

## Open

### 1. Gmail SMTP is broken — ticket emails are failing
`apfimac@gmail.com` app password is rejected (535). Buyers complete payment and get
no ticket email. Highest-impact open item. Fix the app password or move to a real
transactional sender (SES/Postmark) — the org is already on AWS/DO.

### 2. No LIVE smoke test per Stripe account
Multi-Stripe is live but never verified end-to-end with real money. Do one live
purchase + refund on each of `cafeteria`, `rifa`, `eventos`, confirming order
completion, inventory decrement, and the ticket email (blocked on #1).

### 3. Password-reset emails point at a route that doesn't exist
`app/Providers/AppServiceProvider.php:27` builds `{frontend_url}/password-reset/{token}?email=`,
but the frontend route is `/reset-password?token=&email=` (`app/pages/reset-password.vue`).
Every reset link 404s. Fix the URL here (one line) — the frontend is already correct.

### 4. GA checkout can oversell
`CheckoutController` does no locking or inventory hold between creating the Stripe
session and the webhook completing the order. Two buyers can take the last ticket
concurrently. Seated events are safe (`ReservationService` locks); GA is not.
Options: short-lived tier hold mirroring `ReservationService`, or a
`lockForUpdate` + capacity re-check in the webhook that fails the order cleanly.

### 5. Production data cleanup
Events ids 1–4 are seeder/demo leftovers (3 still `live` and publicly visible) and
id 10 is a draft duplicate. Verify no orders reference them, then remove.

---

## Shipped

- **Multi-Stripe, 3 accounts** (2026-07) — `cafeteria` / `rifa` / `eventos`, per-account
  webhooks at `/webhooks/stripe/{account}`, `stripe_account` on events + orders.
  Prod cut over 2026-07-17; 13 events moved to Taquilla Virtual; live webhooks
  registered and verified on all three. The org's 4th account "Tiendita" is
  deliberately NOT routed by this platform.
  Pre-separación orders (1,313) keep `stripe_account = NULL` on purpose — do not backfill.
- **Parcialidades** (2026-07) — `depends_on_tier_id` on ticket_tiers, cycle/depth-3
  validation, eligibility endpoint, checkout enforcement. Matched by clave del alumno only.
- **Per-event checkout fields** (2026-07) — `checkout_settings` JSON on events,
  `student_key` (normalized, indexed) on order_items, exports and attendee search updated.
- **Two-level reports** (2026-07) — `config/reports.php` drives the summary column set
  served to `viewer` (coordinadoras); admins get the full set.
- **Timezone pinned to America/Tijuana** (2026-07) for new events.
- Three misdated `2025_01_15_*` migrations renamed to `2026_01_15_*` with `hasColumn`
  guards (fresh installs broke otherwise; prod-safe to re-run).
- Legacy webhook path no longer touches the dropped `events.tickets_sold` column.
