<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportTicketApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_ticket_and_duplicate_submission_is_idempotent(): void
    {
        $customer = $this->userWithRole('customer');
        ContactMessage::create([
            'full_name' => 'Legacy Contact',
            'contact' => 'legacy@example.com',
            'subject' => 'other',
            'message' => 'Existing contact record.',
            'locale' => 'en',
            'status' => 'new',
        ]);

        $payload = [
            'subject' => 'order',
            'message' => 'Where is my order?',
            'locale' => 'en',
            'client_message_id' => (string) Str::uuid(),
        ];

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/support/tickets', $payload)
            ->assertCreated()
            ->assertJsonPath('data.ticket.status', 'open')
            ->assertJsonPath('data.ticket.subject', 'order');

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/support/tickets', $payload)
            ->assertOk();

        $this->assertDatabaseCount('support_tickets', 1);
        $this->assertDatabaseCount('support_ticket_messages', 1);
        $this->assertDatabaseHas('contact_messages', [
            'full_name' => 'Legacy Contact',
            'message' => 'Existing contact record.',
        ]);
    }

    public function test_ticket_creation_requires_customer_role_and_valid_category(): void
    {
        $withoutRole = User::factory()->create();
        $this->actingAs($withoutRole, 'sanctum')
            ->postJson('/api/support/tickets', [
                'subject' => 'invalid',
                'message' => 'Request.',
                'locale' => 'fa',
                'client_message_id' => (string) Str::uuid(),
            ])
            ->assertForbidden();

        $customer = $this->userWithRole('customer');
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/support/tickets', [
                'subject' => 'invalid',
                'message' => 'Request.',
                'locale' => 'fa',
                'client_message_id' => (string) Str::uuid(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject']);
    }

    public function test_customer_cannot_read_or_reply_to_another_customers_ticket(): void
    {
        $owner = $this->userWithRole('customer');
        $other = $this->userWithRole('customer');
        $ticket = $this->ticketFor($owner);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/support/tickets/{$ticket->id}")
            ->assertNotFound();

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/support/tickets/{$ticket->id}/messages", [
                'message' => 'Unauthorized reply.',
                'client_message_id' => (string) Str::uuid(),
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('support_ticket_messages', 1);
    }

    public function test_customer_reply_and_admin_conversation_are_chronological_and_include_sender_details(): void
    {
        $customer = $this->userWithRole('customer');
        $admin = $this->userWithRole('admin', true, true);
        $ticket = $this->ticketFor($customer);
        $replyKey = (string) Str::uuid();
        $customerReply = [
            'message' => 'Follow-up from customer.',
            'client_message_id' => $replyKey,
        ];

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/support/tickets/{$ticket->id}/messages", $customerReply)
            ->assertCreated();
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/support/tickets/{$ticket->id}/messages", $customerReply)
            ->assertOk();

        $adminReply = [
            'message' => 'Reply from support.',
            'client_message_id' => (string) Str::uuid(),
        ];
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/support-tickets/{$ticket->id}/messages", $adminReply)
            ->assertCreated();
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/support-tickets/{$ticket->id}/messages", $adminReply)
            ->assertOk();

        $this->assertDatabaseCount('support_ticket_messages', 3);

        $customerList = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/support/tickets');
        $customerList->assertJsonPath('data.0.unread_count', 1);
        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/support/tickets/{$ticket->id}")
            ->assertOk();
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/support/tickets')
            ->assertJsonPath('data.0.unread_count', 0);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/support-tickets')
            ->assertJsonPath('data.0.unread_count', 2);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/support-tickets/{$ticket->id}");

        $response->assertOk()
            ->assertJsonPath('data.ticket.customer.email', $customer->email)
            ->assertJsonPath('data.messages.0.sender_role', 'customer')
            ->assertJsonPath('data.messages.1.sender_role', 'customer')
            ->assertJsonPath('data.messages.2.sender_role', 'admin')
            ->assertJsonPath('data.messages.2.sender_name', trim($admin->first_name . ' ' . $admin->last_name));
    }

    public function test_only_authorized_admin_can_close_and_closed_tickets_are_permanently_read_only(): void
    {
        $customer = $this->userWithRole('customer');
        $otherCustomer = $this->userWithRole('customer');
        $ticket = $this->ticketFor($customer);

        $this->actingAs($customer, 'sanctum')
            ->patchJson("/api/admin/support-tickets/{$ticket->id}/close")
            ->assertForbidden();

        $admin = $this->userWithRole('admin', true, true);
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/support-tickets/{$ticket->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/support/tickets/{$ticket->id}/messages", [
                'message' => 'Attempt after close.',
                'client_message_id' => (string) Str::uuid(),
            ])
            ->assertStatus(409);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/support-tickets/{$ticket->id}/messages", [
                'message' => 'Attempt after close.',
                'client_message_id' => (string) Str::uuid(),
            ])
            ->assertStatus(409);

        $this->actingAs($otherCustomer, 'sanctum')
            ->patchJson("/api/admin/support-tickets/{$ticket->id}/close")
            ->assertForbidden();

        $this->assertDatabaseCount('support_ticket_messages', 1);
        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'status' => 'closed',
        ]);
    }

    public function test_admin_ticket_apis_require_support_permission(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/support-tickets')
            ->assertForbidden();
    }

    public function test_support_staff_with_ticket_permission_cannot_close_a_ticket(): void
    {
        $customer = $this->userWithRole('customer');
        $support = $this->userWithRole('support', true);
        $ticket = $this->ticketFor($customer);

        $this->actingAs($support, 'sanctum')
            ->getJson('/api/admin/support-tickets')
            ->assertOk();

        $this->actingAs($support, 'sanctum')
            ->postJson("/api/admin/support-tickets/{$ticket->id}/messages", [
                'message' => 'Reply without write permission.',
                'client_message_id' => (string) Str::uuid(),
            ])
            ->assertForbidden();

        $support->roles()->first()->permissions()->attach(
            Permission::create([
                'name' => 'Reply to Support Tickets',
                'slug' => 'support_tickets.reply',
            ])
        );

        $this->actingAs($support, 'sanctum')
            ->postJson("/api/admin/support-tickets/{$ticket->id}/messages", [
                'message' => 'Reply with explicit write permission.',
                'client_message_id' => (string) Str::uuid(),
            ])
            ->assertCreated();

        $this->actingAs($support, 'sanctum')
            ->patchJson("/api/admin/support-tickets/{$ticket->id}/close")
            ->assertForbidden();
    }

    private function userWithRole(
        string $slug,
        bool $grantContactMessagePermission = false,
        bool $grantSupportTicketReplyPermission = false
    ): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug)]
        );

        if ($grantContactMessagePermission) {
            $permission = Permission::create([
                'name' => 'View contact messages',
                'slug' => 'contact_messages.view',
            ]);
            $role->permissions()->attach($permission);
        }

        if ($grantSupportTicketReplyPermission) {
            $permission = Permission::create([
                'name' => 'Reply to Support Tickets',
                'slug' => 'support_tickets.reply',
            ]);
            $role->permissions()->attach($permission);
        }

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function ticketFor(User $customer): SupportTicket
    {
        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'subject' => 'order',
            'locale' => 'en',
            'status' => 'open',
        ]);

        $message = $ticket->messages()->create([
            'sender_id' => $customer->id,
            'sender_role' => 'customer',
            'sender_name' => trim($customer->first_name . ' ' . $customer->last_name),
            'client_message_id' => (string) Str::uuid(),
            'message' => 'Initial ticket message.',
        ]);
        $ticket->update([
            'customer_last_read_message_id' => $message->id,
        ]);

        return $ticket;
    }
}
