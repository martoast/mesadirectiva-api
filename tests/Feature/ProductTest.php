<?php

namespace Tests\Feature;

use App\Mail\OrderReceipt;
use App\Mail\OrderTickets;
use App\Models\Event;
use App\Models\Group;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->group = Group::create(['name' => 'Tienda', 'slug' => 'tienda', 'created_by' => $this->admin->id]);
        Sanctum::actingAs($this->admin);
    }

    public function test_product_is_forced_onto_cafeteria_without_a_date(): void
    {
        $response = $this->postJson('/events', [
            'kind' => 'product',
            'name' => 'Playera deportiva',
            'group_id' => $this->group->id,
            'stripe_account' => 'eventos',
            'seating_type' => 'seated',
            'location' => ['name' => 'Cafetería', 'instructions' => 'Recoger en recreo'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('event.kind', 'product')
            ->assertJsonPath('event.stripe_account', 'cafeteria')
            ->assertJsonPath('event.checkout_settings.require_student_fields', true)
            ->assertJsonPath('event.checkout_settings.require_attendee_note', false)
            ->assertJsonPath('event.seating_type', 'general_admission')
            ->assertJsonPath('event.ends_at', null);
    }

    public function test_product_without_group_gets_a_default_group(): void
    {
        $this->postJson('/events', ['kind' => 'product', 'name' => 'Termo'])
            ->assertCreated()
            ->assertJsonPath('event.group.id', $this->group->id);
    }

    public function test_events_still_require_dates(): void
    {
        $this->postJson('/events', [
            'name' => 'Kermés',
            'group_id' => $this->group->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['starts_at', 'ends_at']);

        $this->postJson('/events', [
            'name' => 'Kermés',
            'group_id' => $this->group->id,
            'starts_at' => now()->addDay()->toIso8601String(),
            'ends_at' => now()->addDays(2)->toIso8601String(),
            'stripe_account' => 'no-existe',
        ])->assertUnprocessable()->assertJsonValidationErrors(['stripe_account']);
    }

    public function test_product_cannot_be_moved_off_cafeteria(): void
    {
        $product = $this->makeProduct();

        $this->putJson("/events/{$product->slug}", ['stripe_account' => 'eventos'])->assertOk();

        $this->assertSame('cafeteria', $product->fresh()->stripe_account);
    }

    public function test_admin_and_public_lists_filter_by_kind(): void
    {
        $product = $this->makeProduct(['status' => 'live']);
        $event = Event::create([
            'name' => 'Kermés',
            'group_id' => $this->group->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'status' => 'live',
            'created_by' => $this->admin->id,
        ]);

        $this->getJson('/events')->assertJsonCount(1, 'events')->assertJsonPath('events.0.id', $event->id);
        $this->getJson('/events?kind=product')->assertJsonCount(1, 'events')->assertJsonPath('events.0.id', $product->id);
        $this->getJson('/public/events?kind=product')->assertJsonCount(1, 'events')->assertJsonPath('events.0.id', $product->id);
        $this->getJson('/public/events')->assertJsonCount(2, 'events');
    }

    public function test_product_stops_selling_after_available_until(): void
    {
        $product = $this->makeProduct(['status' => 'live', 'ends_at' => now()->subMinute()]);
        TicketTier::create(['event_id' => $product->id, 'name' => 'Talla M', 'price' => 350]);

        $this->assertFalse($product->canPurchase());
        $this->assertSame('sales_ended', $product->getPurchaseBlockedReason());

        $product->update(['ends_at' => null]);
        $this->assertTrue($product->fresh()->canPurchase());
    }

    public function test_receipt_mail_is_used_for_products(): void
    {
        $product = $this->makeProduct(['location' => ['name' => 'Cafetería', 'instructions' => 'Recoger en recreo']]);
        $tier = TicketTier::create(['event_id' => $product->id, 'name' => 'Talla M', 'price' => 350]);
        $order = Order::create([
            'event_id' => $product->id,
            'customer_name' => 'Ana',
            'customer_email' => 'ana@example.com',
            'status' => 'completed',
            'subtotal' => 350,
            'total' => 350,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => 'ticket',
            'ticket_tier_id' => $tier->id,
            'item_name' => 'Playera deportiva - Talla M',
            'quantity' => 1,
            'unit_price' => 350,
            'total_price' => 350,
            'attendee_name' => 'Luis',
            'student_key' => '3B-12',
        ]);

        $mail = new OrderReceipt($order);
        $mail->assertHasSubject('Comprobante de compra: Playera deportiva');
        $mail->assertSeeInHtml('Talla M');
        $mail->assertSeeInHtml('Clave 3B-12');
        $mail->assertSeeInHtml('Entrega: Cafetería');
        $mail->assertSeeInHtml('Recoger en recreo');
        $this->assertEmpty($mail->attachments);
        $this->assertNotInstanceOf(OrderTickets::class, $mail);
    }

    private function makeProduct(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'kind' => 'product',
            'name' => 'Playera deportiva',
            'group_id' => $this->group->id,
            'starts_at' => now(),
            'ends_at' => null,
            'stripe_account' => 'cafeteria',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ], $overrides));
    }
}
