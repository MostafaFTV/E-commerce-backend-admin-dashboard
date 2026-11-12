<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Show Profile
    |--------------------------------------------------------------------------
    */

    public function test_guest_cannot_view_profile(): void
    {
        $response = $this->getJson(
            '/api/profile'
        );

        $response->assertUnauthorized();
    }

    public function test_customer_can_view_their_profile(): void
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+33123456789',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson('/api/profile');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $user->id
            )
            ->assertJsonPath(
                'data.first_name',
                'John'
            )
            ->assertJsonPath(
                'data.last_name',
                'Doe'
            )
            ->assertJsonPath(
                'data.email',
                'john@example.com'
            )
            ->assertJsonPath(
                'data.phone',
                '+33123456789'
            );

        $response->assertJsonMissingPath(
            'data.password'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Profile
    |--------------------------------------------------------------------------
    */

    public function test_customer_can_update_profile(): void
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+33123456789',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                '/api/profile',
                [
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                    'email' => 'john@example.com',
                    'phone' => '+33123456789',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Profile updated successfully.'
            )
            ->assertJsonPath(
                'data.first_name',
                'Jane'
            )
            ->assertJsonPath(
                'data.last_name',
                'Smith'
            )
            ->assertJsonPath(
                'data.email',
                'john@example.com'
            )
            ->assertJsonPath(
                'data.phone',
                '+33123456789'
            );

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->id,
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'john@example.com',
                'phone' => '+33123456789',
            ]
        );
    }

    public function test_customer_cannot_change_email_directly(): void
    {
        $user = User::factory()->create([
            'email' => 'original@example.com',
            'phone' => '+33123456789',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson('/api/profile', [
                'first_name' => 'Changed',
                'email' => 'new@example.com',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'original@example.com',
            'phone' => '+33123456789',
            'first_name' => $user->first_name,
        ]);
    }

    public function test_customer_cannot_change_phone_directly(): void
    {
        $user = User::factory()->create([
            'email' => 'original@example.com',
            'phone' => '+33123456789',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson('/api/profile', [
                'last_name' => 'Changed',
                'phone' => '+33987654321',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'original@example.com',
            'phone' => '+33123456789',
            'last_name' => $user->last_name,
        ]);
    }
    public function test_customer_can_partially_update_profile(): void
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+33123456789',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                '/api/profile',
                [
                    'first_name' => 'Jane',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.first_name',
                'Jane'
            )
            ->assertJsonPath(
                'data.last_name',
                'Doe'
            )
            ->assertJsonPath(
                'data.email',
                'john@example.com'
            )
            ->assertJsonPath(
                'data.phone',
                '+33123456789'
            );
    }

    public function test_customer_cannot_remove_contact_details_directly(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'phone' => '+33123456789',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson('/api/profile', [
                'email' => null,
                'phone' => null,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'john@example.com',
            'phone' => '+33123456789',
        ]);
    }
    public function test_duplicate_email_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
        ]);

        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                '/api/profile',
                [
                    'email' => 'existing@example.com',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        $user = User::factory()->create([
            'phone' => '+33111111111',
        ]);

        User::factory()->create([
            'phone' => '+33222222222',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                '/api/profile',
                [
                    'phone' => '+33222222222',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'phone',
            ]);
    }

    public function test_customer_can_keep_their_existing_email_and_phone(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'phone' => '+33123456789',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                '/api/profile',
                [
                    'email' => 'john@example.com',
                    'phone' => '+33123456789',
                ]
            );

        $response->assertOk();

        $response->assertJsonPath(
            'data.email',
            'john@example.com'
        );

        $response->assertJsonPath(
            'data.phone',
            '+33123456789'
        );
    }

    public function test_invalid_profile_data_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                '/api/profile',
                [
                    'first_name' => '',
                    'last_name' => '',
                    'email' => 'not-an-email',
                    'phone' => str_repeat('1', 31),
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'first_name',
                'last_name',
                'email',
                'phone',
            ]);
    }
}