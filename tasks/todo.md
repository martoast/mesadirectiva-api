# Roadmap (updated 2026-07-15)

Five workstreams, roughly in dependency order. Frontend counterpart plan: `app/tasks/todo.md`.

---

## 1. Multi-Stripe: 3 separate accounts

Money routing per product/event. Accounts:
| Key | Purpose |
|-----|---------|
| `cafeteria` | Cafetería / daily flow: prepaid food, promos, general sales (CURRENT account/keys) |
| `rifa` | Raffles — income fully isolated |
| `eventos` | First communion, graduations, paseos, all events (NEW account) |

### Status (2026-07-15, tested E2E)
Code COMPLETE and TESTED locally (fresh Sail stack, all migrations, real browser E2E purchase on the eventos route with test card — order completed via `/webhooks/stripe/eventos`, inventory + student_key + ticket code verified; parcialidades unlock verified in UI and API). Live keys wired: config falls back to `STRIPE_KEY_RIFA_ENTRE_AMIGOS` / `STRIPE_KEY_TAQUILLA_VIRTUAL` env names. Local `.env` has a clearly marked TEMP block pointing rifa/eventos at the cafeteria TEST keys + stripe-cli webhook secret — DELETE the temp block before deploying.
Remaining for production:
- [x] LIVE webhook endpoints registered + verified via Stripe API on all three accounts (2026-07-16). Secrets documented in local .env next to the live keys; set them in the DO prod env. NOTE: the org also has a 4th Stripe account "Tiendita" — deliberately NOT routed by this platform (confirmed with Alex); its stray webhook endpoint should be deleted from that dashboard.
- [x] whsec values set in DO env; prod deploy + migrations done; webhook routes verified live (2026-07-17)
- [ ] Fix Gmail SMTP: `apfimac@gmail.com` app password is rejected (535) — ticket emails currently fail
- [ ] Prod deploy: run migrations (note: three misdated 2025_01_15 migrations were renamed to 2026_01_15 with hasColumn guards — safe re-run on prod)
- [x] 13 events (all 2026, all eventos-type) moved to Taquilla Virtual (2026-07-17)
- [ ] One LIVE-mode smoke purchase + refund per account after DNS/webhooks are set

### API changes
1. **Config** — `config/services.php`: replace flat `stripe` block with
   ```php
   'stripe' => [
     'accounts' => [
       'cafeteria' => ['key' => env('STRIPE_CAFETERIA_KEY'), 'secret' => env('STRIPE_CAFETERIA_SECRET'), 'webhook_secret' => env('STRIPE_CAFETERIA_WEBHOOK_SECRET')],
       'rifa'      => [...RIFA envs...],
       'eventos'   => [...EVENTOS envs...],
     ],
     'default' => env('STRIPE_DEFAULT_ACCOUNT', 'eventos'),
   ]
   ```
   Map current `STRIPE_KEY/SECRET/WEBHOOK_SECRET` values to the `cafeteria` envs.
2. **Migration** — `stripe_account` string column on `events` (default `'cafeteria'`, backfill below) AND on `orders` (set at checkout; needed for refund tracing + reports).
3. **StripeService rework** — CRITICAL: constructor currently calls `Stripe::setApiKey()` (global static). Refactor to hold a `\Stripe\StripeClient` per account: `StripeService::for(string $account)` or pass account into each method. Webhook verification (`constructWebhookEvent`) takes the account's `webhook_secret`.
4. **CheckoutController** — resolve account from `$event->stripe_account`, create the session on that account, persist `stripe_account` on the order.
5. **WebhookController** — new route `POST /webhooks/stripe/{account}` (validate account key exists), verify signature with that account's secret. Keep legacy `POST /webhooks/stripe` aliased to `cafeteria` until Stripe dashboards are updated.
   - While in here: fix the latent bug where the legacy GA path increments `events.tickets_sold` (column was dropped in `simplify_events`) — remove that branch.
6. **Requests/Resource** — `stripe_account` (`in:cafeteria,rifa,eventos`) in Store/UpdateEventRequest; expose in EventResource. `EventController@publish` should create the Stripe product on the event's account (or drop product creation entirely — checkout uses dynamic `price_data`, product ids are vestigial).
7. **Data migration** — current-year events → `stripe_account = 'eventos'`; null out their `stripe_product_id`/`stripe_price_id` (they belong to the old account).
8. **Pre-release testing** — 3 sets of keys in staging `.env`; `stripe listen`/dashboard webhook per account; run one test purchase + refund per account and verify order completion, ticket email, and inventory per path (GA tier + seated).

---

## 2. Parcialidades dependientes (sequential tier payments) — DONE (2026-07-15)

Implemented: `depends_on_tier_id` FK on ticket_tiers (nullOnDelete), cycle/depth-3/same-event validation in TicketTierController, `prerequisiteChain()`/`missingPrerequisitesForStudent()` on TicketTier, public eligibility endpoint `GET /public/events/{slug}/ticket-tiers/{tierId}/eligibility?student_key=`, per-ticket enforcement in checkout (Spanish 422s), `depends_on_tier` in TicketTierResource. Matching: clave del alumno only. Original plan below.

Split one event into 2–3 payments modeled as tiers. Pago 2 purchasable only after Pago 1 completed; Pago 3 requires 1 and 2.

1. **Migration** — `depends_on_tier_id` nullable FK (nullOnDelete) on `ticket_tiers`, self-referencing.
2. **Validation** — Store/UpdateTicketTierRequest: must belong to same event, no cycles (A→B→A), max chain length 3.
3. **Matching identity** — CONFIRMED with Alex: match by **clave del alumno** only (`OrderItem::normalizeStudentKey()` already exists; `order_items.student_key` is indexed). Workstream 3 shipped the field.
4. **Eligibility endpoint** — `GET /public/events/{slug}/tiers/{tierId}/eligibility?student_key=...&email=...` → `{ eligible: bool, missing_tier: {...} }`. Checks a completed order exists containing the prerequisite tier for that student key.
5. **Checkout enforcement** — in `createGeneralAdmissionCheckout`, before accepting a dependent tier: same check server-side; reject 422 with a clear Spanish message if prerequisite not met.
6. **TicketTierResource** — expose `depends_on_tier_id` + `depends_on_tier` (id/name) so the frontend can render locked states.

Matching rule decided: clave del alumno only.

---

## 3. Checkout improvements — DONE (2026-07-15)

Implemented: `checkout_settings` JSON on events (`collect_student_fields` default true, `require_student_fields`, `require_attendee_note`), `student_key` (normalized uppercase, indexed) on order_items, required-field enforcement in CreateCheckoutRequest with Spanish messages, student_key in Attendee/OrderItem resources and attendee search. Original plan below.

### Original plan

1. **Per-event checkout field settings** — `checkout_settings` JSON on `events` (same pattern as `email_settings`):
   ```json
   { "collect_student_fields": true, "require_student_name": true, "require_student_key": true }
   ```
   Off ⇒ external buyers see no student fields. Validate in UpdateEventRequest; expose in EventResource.
2. **Order item fields** — add `student_name` + `student_key` columns to `order_items` (keep `attendee_name`/`attendee_note` for tables/seats + free-form). CheckoutRequest validates them as required when the event's settings say so.
3. **Attendees/reports plumbing** — include student fields in AttendeeResource, OrderItemResource, exports.

---

## 4. Reports with two access levels — DONE (2026-07-15)

Implemented: `config/reports.php` summary column map (edit to change coordinator columns, no code needed), `ReportService::getSummaryReport()` (one row per sold ticket), viewer branch in ReportController sales/orders + exports (`SummaryReportExport`, filename `reporte-resumido-*.xlsx`), `report_level` field in responses. Original plan below.

- **Full report (admin/super_admin)** — everything current: hora, # orden, transacción (`stripe_payment_intent_id`), plus `stripe_account` from workstream 1.
- **Summary report (viewer = coordinadoras de sección)** — only the agreed columns (initial set: evento, alumno/asistente, clave, tier/pago, cantidad, total, fecha). No Stripe/order internals.

1. **ReportController/ReportService** — branch on `$request->user()` role: viewers get the reduced column payload; admins get full. Same for exports: new `SummaryOrdersExport` used when role is viewer.
2. **Access** — viewers are currently blocked from admin UI entirely (frontend `admin` middleware). Backend already allows viewers on `/reports/*` (auth only) scoped by `accessibleBy` — verify that scoping and keep it.
3. Column set for the summary report is team-defined — build it config-driven (array in `config/reports.php`) so changing columns doesn't require code edits.

---

## 5. Fixed timezone (Tijuana) — DONE (2026-07-15)

StoreEventRequest force-merges `timezone = America/Tijuana` on create; frontend selector removed. Existing events keep their stored timezone (edits don't rewrite it). Optional data fix below NOT run.

### Original plan

1. StoreEventRequest: default/force `timezone = 'America/Tijuana'` (drop from user input or ignore incoming value).
2. Optional data fix: update current-year events to `America/Tijuana` (LA and Tijuana share offsets, so display is unchanged).
3. Frontend removes the selector (see app todo).

---

# Previously Completed
- Email & Ticket CMS (per-event email/PDF customization)
- E-Ticket PDF + QR check-in, scanner endpoint
- Eventbrite-style tiers, reorder, hide_available_quantity
- Attendees check-in system
