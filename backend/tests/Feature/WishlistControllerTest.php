<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_wishlist(): void
    {
        $response = $this->getJson(
            '/api/wishlist'
        );

        $response->assertUnauthorized();
    }

    public function test_customer_can_view_empty_wishlist(): void
    {
        $customer = $this->createActiveUser();

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->getJson('/api/wishlist');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                null
            )
            ->assertJsonCount(
                0,
                'data.items'
            );
    }

    public function test_customer_can_add_product_to_wishlist(): void
    {
        $customer = $this->createActiveUser();

        $product = $this->createActiveProduct();

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(
                '/api/wishlist/items',
                [
                    'product_id' => $product->id,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Product added to wishlist.'
            )
            ->assertJsonPath(
                'item.product_id',
                $product->id
            );

        $this->assertDatabaseHas(
            'wishlists',
            [
                'user_id' => $customer->id,
            ]
        );

        $wishlist = Wishlist::where(
            'user_id',
            $customer->id
        )->firstOrFail();

        $this->assertDatabaseHas(
            'wishlist_items',
            [
                'wishlist_id' => $wishlist->id,
                'product_id' => $product->id,
            ]
        );
    }

    public function test_duplicate_product_is_not_added_twice(): void
    {
        $customer = $this->createActiveUser();

        $product = $this->createActiveProduct();

        $firstResponse = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(
                '/api/wishlist/items',
                [
                    'product_id' => $product->id,
                ]
            );

        $firstResponse->assertCreated();

        $secondResponse = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(
                '/api/wishlist/items',
                [
                    'product_id' => $product->id,
                ]
            );

        $secondResponse
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Product is already in the wishlist.'
            );

        $wishlist = Wishlist::where(
            'user_id',
            $customer->id
        )->firstOrFail();

        $this->assertSame(
            1,
            WishlistItem::where(
                'wishlist_id',
                $wishlist->id
            )
            ->where(
                'product_id',
                $product->id
            )
            ->count()
        );
    }

    public function test_customer_can_remove_product_from_wishlist(): void
    {
        $customer = $this->createActiveUser();

        $product = $this->createActiveProduct();

        $wishlist = Wishlist::create([
            'user_id' => $customer->id,
        ]);

        $item = WishlistItem::create([
            'wishlist_id' => $wishlist->id,
            'product_id' => $product->id,
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->deleteJson(
                "/api/wishlist/items/{$item->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Product removed from wishlist.'
            );

        $this->assertDatabaseMissing(
            'wishlist_items',
            [
                'id' => $item->id,
            ]
        );
    }

    public function test_customer_cannot_remove_another_customers_wishlist_item(): void
    {
        $customer = $this->createActiveUser();

        $otherCustomer = $this->createActiveUser();

        $product = $this->createActiveProduct();

        $wishlist = Wishlist::create([
            'user_id' => $otherCustomer->id,
        ]);

        $item = WishlistItem::create([
            'wishlist_id' => $wishlist->id,
            'product_id' => $product->id,
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->deleteJson(
                "/api/wishlist/items/{$item->id}"
            );

        $response
            ->assertNotFound()
            ->assertJsonPath(
                'message',
                'Wishlist item not found.'
            );

        $this->assertDatabaseHas(
            'wishlist_items',
            [
                'id' => $item->id,
            ]
        );
    }

    public function test_customer_can_clear_their_wishlist(): void
    {
        $customer = $this->createActiveUser();

        $productOne = $this->createActiveProduct();
        $productTwo = $this->createActiveProduct();

        $wishlist = Wishlist::create([
            'user_id' => $customer->id,
        ]);

        WishlistItem::create([
            'wishlist_id' => $wishlist->id,
            'product_id' => $productOne->id,
            'created_at' => now(),
        ]);

        WishlistItem::create([
            'wishlist_id' => $wishlist->id,
            'product_id' => $productTwo->id,
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->deleteJson('/api/wishlist');

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Wishlist cleared successfully.'
            );

        $this->assertSame(
            0,
            WishlistItem::where(
                'wishlist_id',
                $wishlist->id
            )->count()
        );

        $this->assertDatabaseHas(
            'wishlists',
            [
                'id' => $wishlist->id,
                'user_id' => $customer->id,
            ]
        );
    }

    public function test_customer_cannot_clear_another_customers_wishlist(): void
    {
        $customer = $this->createActiveUser();

        $otherCustomer = $this->createActiveUser();

        $product = $this->createActiveProduct();

        $wishlist = Wishlist::create([
            'user_id' => $otherCustomer->id,
        ]);

        $item = WishlistItem::create([
            'wishlist_id' => $wishlist->id,
            'product_id' => $product->id,
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->deleteJson('/api/wishlist');

        $response->assertNotFound();

        $this->assertDatabaseHas(
            'wishlist_items',
            [
                'id' => $item->id,
            ]
        );
    }

    public function test_invalid_product_cannot_be_added_to_wishlist(): void
    {
        $customer = $this->createActiveUser();

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(
                '/api/wishlist/items',
                [
                    'product_id' => 999999,
                ]
            );

        $response->assertUnprocessable();
    }

    public function test_deleted_product_cannot_be_added_to_wishlist(): void
    {
        $customer = $this->createActiveUser();

        $product = $this->createActiveProduct();

        $product->delete();

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(
                '/api/wishlist/items',
                [
                    'product_id' => $product->id,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'product_id',
            ]);
    }

    public function test_customer_can_view_wishlist_items(): void
    {
        $customer = $this->createActiveUser();

        $productOne = $this->createActiveProduct();
        $productTwo = $this->createActiveProduct();

        $wishlist = Wishlist::create([
            'user_id' => $customer->id,
        ]);

        WishlistItem::create([
            'wishlist_id' => $wishlist->id,
            'product_id' => $productOne->id,
            'created_at' => now()->subMinute(),
        ]);

        WishlistItem::create([
            'wishlist_id' => $wishlist->id,
            'product_id' => $productTwo->id,
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->getJson('/api/wishlist');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $wishlist->id
            )
            ->assertJsonCount(
                2,
                'data.items'
            );
    }

    public function test_inactive_customer_cannot_access_wishlist(): void
    {
        $customer = $this->createUserWithStatus(
            UserStatus::INACTIVE
        );

        $product = $this->createActiveProduct();

        $this
            ->actingAs($customer, 'sanctum')
            ->getJson('/api/wishlist')
            ->assertForbidden();

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(
                '/api/wishlist/items',
                [
                    'product_id' => $product->id,
                ]
            )
            ->assertForbidden();

        $this
            ->actingAs($customer, 'sanctum')
            ->deleteJson('/api/wishlist')
            ->assertForbidden();
    }

    public function test_suspended_customer_cannot_access_wishlist(): void
    {
        $customer = $this->createUserWithStatus(
            UserStatus::SUSPENDED
        );

        $product = $this->createActiveProduct();

        $this
            ->actingAs($customer, 'sanctum')
            ->getJson('/api/wishlist')
            ->assertForbidden();

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(
                '/api/wishlist/items',
                [
                    'product_id' => $product->id,
                ]
            )
            ->assertForbidden();

        $this
            ->actingAs($customer, 'sanctum')
            ->deleteJson('/api/wishlist')
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    public function test_authenticated_wishlist_write_requests_are_rate_limited(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '10.254.251.10',
        ]);

        $customer = $this->createActiveUser();

        for ($i = 1; $i <= 30; $i++) {
            $this
                ->actingAs($customer, 'sanctum')
                ->postJson(
                    '/api/wishlist/items',
                    []
                )
                ->assertUnprocessable();
        }

        $this
            ->actingAs($customer, 'sanctum')
            ->postJson(
                '/api/wishlist/items',
                []
            )
            ->assertStatus(429);
    }

    public function test_authenticated_wishlist_read_requests_are_rate_limited(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '10.254.251.11',
        ]);

        $customer = $this->createActiveUser();

        for ($i = 1; $i <= 60; $i++) {
            $this
                ->actingAs($customer, 'sanctum')
                ->getJson('/api/wishlist')
                ->assertOk();
        }

        $this
            ->actingAs($customer, 'sanctum')
            ->getJson('/api/wishlist')
            ->assertStatus(429);
    }

    private function createActiveUser(): User
    {
        return $this->createUserWithStatus(
            UserStatus::ACTIVE
        );
    }

    private function createUserWithStatus(UserStatus $status): User
    {
        return User::factory()->create([
            'status' => $status,
        ]);
    }

    private function createActiveProduct(): Product
    {
        return Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);
    }
}