<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function test_guest_cannot_view_addresses(): void
    {
        $response = $this->getJson(
            '/api/addresses'
        );

        $response->assertUnauthorized();
    }

    public function test_customer_can_view_their_addresses(): void
    {
        $user = $this->createActiveUser();

        $addressOne = $this->createAddress(
            $user,
            [
                'title' => 'Home',
                'is_default' => true,
            ]
        );

        $addressTwo = $this->createAddress(
            $user,
            [
                'title' => 'Office',
                'is_default' => false,
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/addresses');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'data.0.id',
                $addressOne->id
            )
            ->assertJsonPath(
                'data.0.is_default',
                true
            )
            ->assertJsonPath(
                'data.1.id',
                $addressTwo->id
            )
            ->assertJsonPath(
                'data.1.is_default',
                false
            );
    }

    public function test_customer_cannot_see_another_customers_addresses(): void
    {
        $user = $this->createActiveUser();

        $otherUser = $this->createActiveUser();

        $this->createAddress(
            $otherUser,
            [
                'title' => 'Private Address',
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/addresses');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function test_customer_can_create_address(): void
    {
        $user = $this->createActiveUser();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                '/api/addresses',
                [
                    'title' => 'Home',
                    'recipient_name' => 'John Doe',
                    'phone' => '+33123456789',
                    'address' => '10 Rue de Paris',
                    'city' => 'Paris',
                    'state' => 'Île-de-France',
                    'postal_code' => '75001',
                    'country' => 'France',
                    'is_default' => true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Address created successfully.'
            )
            ->assertJsonPath(
                'data.title',
                'Home'
            )
            ->assertJsonPath(
                'data.recipient_name',
                'John Doe'
            )
            ->assertJsonPath(
                'data.city',
                'Paris'
            )
            ->assertJsonPath(
                'data.is_default',
                true
            );

        $this->assertDatabaseHas(
            'addresses',
            [
                'user_id' => $user->id,
                'title' => 'Home',
                'recipient_name' => 'John Doe',
                'is_default' => true,
            ]
        );
    }

    public function test_customer_cannot_override_user_id_when_creating_address(): void
    {
        $user = $this->createActiveUser();

        $otherUser = $this->createActiveUser();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                '/api/addresses',
                [
                    'user_id' => $otherUser->id,
                    'title' => 'Home',
                    'recipient_name' => 'John Doe',
                    'phone' => '+33123456789',
                    'address' => '10 Rue de Paris',
                    'city' => 'Paris',
                    'state' => 'Île-de-France',
                    'postal_code' => '75001',
                    'country' => 'France',
                    'is_default' => true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.title',
                'Home'
            );

        $this->assertDatabaseHas(
            'addresses',
            [
                'user_id' => $user->id,
                'title' => 'Home',
            ]
        );

        $this->assertDatabaseMissing(
            'addresses',
            [
                'user_id' => $otherUser->id,
                'title' => 'Home',
            ]
        );
    }

    public function test_first_address_becomes_default_automatically(): void
    {
        $user = $this->createActiveUser();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                '/api/addresses',
                [
                    'title' => 'First Address',
                    'recipient_name' => 'John Doe',
                    'phone' => '+33123456789',
                    'address' => '10 Rue de Paris',
                    'city' => 'Paris',
                    'state' => 'Paris Region',
                    'postal_code' => '75001',
                    'country' => 'France',
                    'is_default' => false,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.is_default',
                true
            );

        $this->assertDatabaseHas(
            'addresses',
            [
                'user_id' => $user->id,
                'is_default' => true,
            ]
        );
    }

    public function test_creating_new_default_address_removes_previous_default(): void
    {
        $user = $this->createActiveUser();

        $oldDefault = $this->createAddress(
            $user,
            [
                'title' => 'Old Default',
                'is_default' => true,
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                '/api/addresses',
                [
                    'title' => 'New Default',
                    'recipient_name' => 'John Doe',
                    'phone' => '+33123456789',
                    'address' => '20 Avenue Victor Hugo',
                    'city' => 'Paris',
                    'state' => 'Paris Region',
                    'postal_code' => '75008',
                    'country' => 'France',
                    'is_default' => true,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.is_default',
                true
            );

        $this->assertDatabaseHas(
            'addresses',
            [
                'id' => $oldDefault->id,
                'is_default' => false,
            ]
        );

        $this->assertDatabaseCount(
            'addresses',
            2
        );

        $this->assertDatabaseHas(
            'addresses',
            [
                'user_id' => $user->id,
                'title' => 'New Default',
                'is_default' => true,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function test_customer_can_view_their_own_address(): void
    {
        $user = $this->createActiveUser();

        $address = $this->createAddress(
            $user
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/addresses/{$address->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $address->id
            )
            ->assertJsonPath(
                'data.recipient_name',
                $address->recipient_name
            );
    }

    public function test_customer_cannot_view_another_customers_address(): void
    {
        $user = $this->createActiveUser();

        $otherUser = $this->createActiveUser();

        $address = $this->createAddress(
            $otherUser
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/addresses/{$address->id}"
            );

        $response->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function test_customer_can_update_their_own_address(): void
    {
        $user = $this->createActiveUser();

        $address = $this->createAddress(
            $user,
            [
                'title' => 'Old Home',
                'city' => 'Lyon',
                'is_default' => true,
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/addresses/{$address->id}",
                [
                    'title' => 'New Home',
                    'city' => 'Paris',
                    'postal_code' => '75001',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Address updated successfully.'
            )
            ->assertJsonPath(
                'data.title',
                'New Home'
            )
            ->assertJsonPath(
                'data.city',
                'Paris'
            )
            ->assertJsonPath(
                'data.postal_code',
                '75001'
            )
            ->assertJsonPath(
                'data.is_default',
                true
            );

        $this->assertDatabaseHas(
            'addresses',
            [
                'id' => $address->id,
                'title' => 'New Home',
                'city' => 'Paris',
                'is_default' => true,
            ]
        );
    }

    public function test_customer_cannot_change_address_owner_when_updating(): void
    {
        $user = $this->createActiveUser();

        $otherUser = $this->createActiveUser();

        $address = $this->createAddress(
            $user,
            [
                'title' => 'Home',
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/addresses/{$address->id}",
                [
                    'user_id' => $otherUser->id,
                    'title' => 'Updated Home',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.title',
                'Updated Home'
            );

        $this->assertDatabaseHas(
            'addresses',
            [
                'id' => $address->id,
                'user_id' => $user->id,
                'title' => 'Updated Home',
            ]
        );

        $this->assertDatabaseMissing(
            'addresses',
            [
                'id' => $address->id,
                'user_id' => $otherUser->id,
            ]
        );
    }

    public function test_customer_can_change_default_address(): void
    {
        $user = $this->createActiveUser();

        $oldDefault = $this->createAddress(
            $user,
            [
                'title' => 'Old Default',
                'is_default' => true,
            ]
        );

        $newAddress = $this->createAddress(
            $user,
            [
                'title' => 'New Address',
                'is_default' => false,
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/addresses/{$newAddress->id}",
                [
                    'is_default' => true,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $newAddress->id
            )
            ->assertJsonPath(
                'data.is_default',
                true
            );

        $this->assertDatabaseHas(
            'addresses',
            [
                'id' => $oldDefault->id,
                'is_default' => false,
            ]
        );

        $this->assertDatabaseHas(
            'addresses',
            [
                'id' => $newAddress->id,
                'is_default' => true,
            ]
        );
    }

    public function test_customer_cannot_update_another_customers_address(): void
    {
        $user = $this->createActiveUser();

        $otherUser = $this->createActiveUser();

        $address = $this->createAddress(
            $otherUser,
            [
                'title' => 'Private Address',
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/addresses/{$address->id}",
                [
                    'title' => 'Hacked Address',
                ]
            );

        $response->assertNotFound();

        $this->assertDatabaseHas(
            'addresses',
            [
                'id' => $address->id,
                'title' => 'Private Address',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function test_customer_can_delete_their_own_address(): void
    {
        $user = $this->createActiveUser();

        $address = $this->createAddress(
            $user,
            [
                'is_default' => false,
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->deleteJson(
                "/api/addresses/{$address->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Address deleted successfully.'
            );

        $this->assertDatabaseMissing(
            'addresses',
            [
                'id' => $address->id,
            ]
        );
    }

    public function test_deleting_default_address_promotes_another_address(): void
    {
        $user = $this->createActiveUser();

        $defaultAddress = $this->createAddress(
            $user,
            [
                'title' => 'Default Address',
                'is_default' => true,
            ]
        );

        $replacementAddress = $this->createAddress(
            $user,
            [
                'title' => 'Replacement Address',
                'is_default' => false,
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->deleteJson(
                "/api/addresses/{$defaultAddress->id}"
            );

        $response->assertOk();

        $this->assertDatabaseMissing(
            'addresses',
            [
                'id' => $defaultAddress->id,
            ]
        );

        $this->assertDatabaseHas(
            'addresses',
            [
                'id' => $replacementAddress->id,
                'is_default' => true,
            ]
        );
    }

    public function test_customer_cannot_delete_another_customers_address(): void
    {
        $user = $this->createActiveUser();

        $otherUser = $this->createActiveUser();

        $address = $this->createAddress(
            $otherUser
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->deleteJson(
                "/api/addresses/{$address->id}"
            );

        $response->assertNotFound();

        $this->assertDatabaseHas(
            'addresses',
            [
                'id' => $address->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public function test_address_creation_requires_required_fields(): void
    {
        $user = $this->createActiveUser();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                '/api/addresses',
                []
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                [
                    'recipient_name',
                    'phone',
                    'address',
                    'city',
                    'state',
                    'postal_code',
                    'country',
                ]
            );
    }

    public function test_address_creation_rejects_invalid_field_types(): void
    {
        $user = $this->createActiveUser();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                '/api/addresses',
                [
                    'recipient_name' => '',
                    'phone' => '',
                    'address' => '',
                    'city' => '',
                    'postal_code' => '',
                    'country' => '',
                    'is_default' => 'not-a-boolean',
                ]
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors(
            [
                'is_default',
            ]
        );
    }

    public function test_address_creation_rejects_blank_location_fields(): void
    {
        $user = $this->createActiveUser();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/addresses', [
                'recipient_name' => 'John Doe',
                'phone' => '+33123456789',
                'state' => ' ',
                'city' => '',
                'address' => ' ',
                'postal_code' => '',
                'country' => 'Iran',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'state',
                'city',
                'address',
                'postal_code',
            ]);

        $this->assertDatabaseCount('addresses', 0);
    }
    public function test_partial_update_does_not_require_all_fields(): void
    {
        $user = $this->createActiveUser();

        $address = $this->createAddress(
            $user,
            [
                'title' => 'Home',
                'city' => 'Paris',
            ]
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/addresses/{$address->id}",
                [
                    'title' => 'Updated Home',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.title',
                'Updated Home'
            )
            ->assertJsonPath(
                'data.city',
                'Paris'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createActiveUser(
        array $attributes = []
    ): User {
        return User::factory()->create(
            array_merge(
                [
                    'status' => UserStatus::ACTIVE,
                ],
                $attributes
            )
        );
    }

    private function createAddress(
        User $user,
        array $attributes = []
    ): Address {
        return Address::create(
            array_merge(
                [
                    'user_id' => $user->id,
                    'title' => 'Home',
                    'recipient_name' => 'John Doe',
                    'phone' => '+33123456789',
                    'address' => '10 Rue de Paris',
                    'city' => 'Paris',
                    'state' => 'Île-de-France',
                    'postal_code' => '75001',
                    'country' => 'France',
                    'is_default' => false,
                ],
                $attributes
            )
        );
    }
}
