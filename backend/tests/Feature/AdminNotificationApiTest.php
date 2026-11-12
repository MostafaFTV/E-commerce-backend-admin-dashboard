<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ContactMessage;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_and_support_notifications_preserve_existing_contact_behavior_and_unread_counts(): void
    {
        $admin = $this->adminWithPermissions([
            'contact_messages.view',
        ]);
        ContactMessage::create([
            'full_name' => 'Contact Customer',
            'contact' => 'contact@example.com',
            'subject' => 'order',
            'message' => 'Question about an order.',
            'locale' => 'en',
            'status' => 'new',
        ]);

        $customer = User::factory()->create();
        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'subject' => 'order',
            'locale' => 'en',
            'status' => 'open',
        ]);
        $firstMessage = $ticket->messages()->create([
            'sender_id' => $customer->id,
            'sender_role' => 'customer',
            'sender_name' => 'Customer',
            'client_message_id' => (string) Str::uuid(),
            'message' => 'First request.',
        ]);
        $ticket->messages()->create([
            'sender_id' => $customer->id,
            'sender_role' => 'customer',
            'sender_name' => 'Customer',
            'client_message_id' => (string) Str::uuid(),
            'message' => 'Customer follow-up.',
        ]);
        $ticket->update([
            'admin_last_read_message_id' => $firstMessage->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonFragment([
                'type' => 'contact_message',
                'href' => '/contact-messages/1',
            ])
            ->assertJsonFragment([
                'type' => 'support_ticket',
                'href' => "/support-tickets/{$ticket->id}",
                'is_read' => false,
            ]);

        $this->assertCount(3, $response->json('items'));

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/support-tickets/{$ticket->id}")
            ->assertOk();
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/notifications')
            ->assertJsonPath('count', 1);
    }

    public function test_pending_orders_failed_payments_and_reconciliations_are_deduplicated(): void
    {
        $admin = $this->adminWithPermissions(['orders.view']);
        $failedOrder = $this->pendingOrder('TKB-FAILED');
        Payment::create([
            'order_id' => $failedOrder->id,
            'amount' => 100,
            'currency' => 'IRT',
            'method' => 'gateway',
            'status' => 'failed',
        ]);

        $reviewOrder = $this->pendingOrder('TKB-REVIEW');
        $payment = Payment::create([
            'order_id' => $reviewOrder->id,
            'amount' => 200,
            'currency' => 'IRT',
            'method' => 'gateway',
            'status' => 'failed',
        ]);
        DB::table('payment_reconciliations')->insert([
            'payment_id' => $payment->id,
            'order_id' => $reviewOrder->id,
            'gateway_transaction_id' => 'gateway-review',
            'amount' => 200,
            'currency' => 'IRT',
            'reason' => 'order_conflict',
            'status' => 'requires_review',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paidOrder = $this->pendingOrder('TKB-RECENT-PAID');
        $paidOrder->update(['status' => 'paid']);
        ActivityLog::create([
            'action' => 'orders.created',
            'description' => 'Order created.',
            'properties' => [
                'order' => [
                    'id' => $paidOrder->id,
                    'order_number' => $paidOrder->order_number,
                ],
            ],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonPath('count', 3);

        $items = collect($response->json('items'))->keyBy('description');
        $this->assertSame('payment_failed', $items['TKB-FAILED']['type']);
        $this->assertSame('/orders/' . $failedOrder->id, $items['TKB-FAILED']['href']);
        $this->assertSame('payment_reconciliation', $items['TKB-REVIEW']['type']);
        $this->assertSame('/orders/' . $reviewOrder->id, $items['TKB-REVIEW']['href']);
        $this->assertSame('order', $items['TKB-RECENT-PAID']['type']);
        $this->assertSame('/orders/' . $paidOrder->id, $items['TKB-RECENT-PAID']['href']);
    }

    public function test_low_out_of_stock_and_restock_transitions_are_reported_once(): void
    {
        $admin = $this->adminWithPermissions(['inventory.view']);
        $lowStock = Inventory::factory()->lowStock()->create();
        $outOfStock = Inventory::factory()->outOfStock()->create();
        $restocked = Inventory::factory()->create([
            'quantity' => 12,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 5,
        ]);

        foreach ([$lowStock, $outOfStock, $restocked] as $inventory) {
            $inventory->variant->product->update(['status' => 'active']);
            $inventory->variant->update(['status' => 'active']);
        }

        ActivityLog::create([
            'action' => 'inventory.stock_added',
            'description' => 'Inventory stock added.',
            'properties' => [
                'inventory' => ['id' => $restocked->id],
                'available_quantity_before' => 0,
                'available_quantity_after' => 12,
            ],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonPath('count', 3);

        $firstItems = collect($response->json('items'))->keyBy('id');
        $this->assertSame('low_stock', $firstItems["stock-low_stock-{$lowStock->id}-{$lowStock->updated_at->getTimestamp()}"]['type']);
        $this->assertSame('out_of_stock', $firstItems["stock-out_of_stock-{$outOfStock->id}-{$outOfStock->updated_at->getTimestamp()}"]['type']);
        $restockItem = $firstItems->first(
            fn (array $item) => $item['type'] === 'stock_replenished'
        );
        $this->assertNotNull($restockItem);
        $this->assertSame("/inventory/{$restocked->id}", $restockItem['href']);

        $secondResponse = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonPath('count', 3);
        $this->assertSame(
            collect($response->json('items'))->pluck('id')->sort()->values()->all(),
            collect($secondResponse->json('items'))->pluck('id')->sort()->values()->all()
        );

        $restocked->update(['reserved_quantity' => 12]);
        ActivityLog::create([
            'action' => 'inventory.stock_reserved',
            'description' => 'Inventory stock reserved.',
            'properties' => [
                'inventory' => ['id' => $restocked->id],
                'available_quantity_before' => 12,
                'available_quantity_after' => 0,
            ],
            'created_at' => now()->addSecond(),
        ]);
        $changedState = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonPath('count', 3);
        $changedItems = collect($changedState->json('items'));
        $this->assertTrue($changedItems->contains(
            fn (array $item) => $item['id'] === "stock-out_of_stock-{$restocked->id}-{$restocked->fresh()->updated_at->getTimestamp()}"
                && $item['type'] === 'out_of_stock'
        ));
        $this->assertFalse($changedItems->contains(
            fn (array $item) => $item['type'] === 'stock_replenished'
        ));
    }

    public function test_notification_queries_enforce_permissions_and_do_not_leak_other_event_types(): void
    {
        $ordersAdmin = $this->adminWithPermissions(['orders.view']);
        ContactMessage::create([
            'full_name' => 'Secret Contact',
            'contact' => 'secret@example.com',
            'subject' => 'other',
            'message' => 'Private contact.',
            'locale' => 'en',
            'status' => 'new',
        ]);
        $this->pendingOrder('TKB-ORDER');

        $this->actingAs($ordersAdmin, 'sanctum')
            ->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonMissing([
                'description' => 'Secret Contact · سایر',
            ]);

        $unauthorized = User::factory()->create();
        $this->actingAs($unauthorized, 'sanctum')
            ->getJson('/api/admin/notifications')
            ->assertForbidden();
    }

    public function test_marking_notifications_read_is_persistent_user_scoped_and_leaves_new_events_unread(): void
    {
        $admin = $this->adminWithPermissions(['contact_messages.view']);
        $otherAdmin = $this->adminWithPermissions(['contact_messages.view']);
        $existingMessage = ContactMessage::create([
            'full_name' => 'Existing Customer',
            'contact' => 'existing@example.com',
            'subject' => 'other',
            'message' => 'Existing message.',
            'locale' => 'fa',
            'status' => 'new',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.is_read', false);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('marked_count', 1);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('items.0.is_read', true);

        $this->actingAs($otherAdmin, 'sanctum')
            ->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.is_read', false);

        $newMessage = ContactMessage::create([
            'full_name' => 'New Customer',
            'contact' => 'new@example.com',
            'subject' => 'order',
            'message' => 'New message.',
            'locale' => 'fa',
            'status' => 'new',
        ]);

        $newEvents = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonPath('count', 1);

        $items = collect($newEvents->json('items'))->keyBy('id');
        $this->assertFalse($items["contact-{$newMessage->id}"]['is_read']);
        $this->assertTrue($items["contact-{$existingMessage->id}"]['is_read']);
        $this->assertSame('new', $existingMessage->fresh()->status);
    }

    private function adminWithPermissions(array $slugs): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin']
        );
        foreach ($slugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug]
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function pendingOrder(string $orderNumber): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'order_number' => $orderNumber,
            'status' => 'pending_payment',
            'subtotal' => 100,
            'discount' => 0,
            'shipping_cost' => 0,
            'tax' => 0,
            'total' => 100,
            'currency' => 'IRT',
            'shipping_address' => ['city' => 'Test'],
            'billing_address' => null,
        ]);
    }
}
