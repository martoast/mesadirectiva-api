# Roadmap — API (updated 2026-09-05)

Frontend counterpart: `app/tasks/todo.md`.

The five 2026-07 workstreams (multi-Stripe, parcialidades, checkout student fields,
two-level reports, Tijuana timezone) all shipped and are live in production.
See "Shipped" at the bottom and `git log` for detail — nothing left to do there.

---

## Open

### 1. No LIVE smoke test per Stripe account
Multi-Stripe is live but never verified end-to-end with real money. Do one live
purchase + refund on each of `cafeteria`, `rifa`, `eventos`, confirming order
completion, inventory decrement, and the ticket email.

### 2. Password-reset emails point at a route that doesn't exist
`app/Providers/AppServiceProvider.php:27` builds `{frontend_url}/password-reset/{token}?email=`,
but the frontend route is `/reset-password?token=&email=` (`app/pages/reset-password.vue`).
Every reset link 404s. Fix the URL here (one line) — the frontend is already correct.

### 3. GA checkout can oversell
`CheckoutController` does no locking or inventory hold between creating the Stripe
session and the webhook completing the order. Two buyers can take the last ticket
concurrently. Seated events are safe (`ReservationService` locks); GA is not.
Options: short-lived tier hold mirroring `ReservationService`, or a
`lockForUpdate` + capacity re-check in the webhook that fails the order cleanly.

### 4. Production data cleanup
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

---

## Not a bug (do not re-investigate)

**Gmail SMTP / "app password rejected (535)"** — an earlier revision of this file listed
this as the top open item. It is not broken and, per Alex (2026-09-05), ticket emails
have been arriving normally the whole time; no one ever reported a missing email.
Verified 2026-09-05: the app password authenticates against `smtp.gmail.com:587`, prod
resolves `mailer=smtp` with the correct host/port/user/from, and the container reaches
Gmail on 587. `MAIL_SCHEME=null` and the absent `MAIL_ENCRYPTION` are both fine —
Laravel 12 derives the scheme from the port (587 → STARTTLS) and ignores
`MAIL_ENCRYPTION` entirely.

Standing caveat, not a defect: `OrderTickets` is sent synchronously inside
`WebhookController` under `catch (\Exception $e)`, which swallows *any* failure —
SMTP, dompdf, QR, or S3 — into one "Failed to send tickets email" log line. If tickets
ever do go missing, that log line is the place to look, and it will not tell you which
of those failed without widening the catch.
