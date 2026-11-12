<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfilePreferencesApiTest extends TestCase
{
    use RefreshDatabase;

    private function createCategory(
        string $slug,
        bool $active = true
    ): int {
        return DB::table('categories')->insertGetId([
            'name' => json_encode([
                'en' => $slug,
                'fa' => $slug,
            ]),
            'slug' => $slug,
            'is_active' => $active,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_profile_returns_default_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.birth_date', null)
            ->assertJsonPath('data.marketing_sms', false)
            ->assertJsonPath('data.preferred_category_ids', []);
    }

    public function test_customer_can_save_and_reload_preferences(): void
    {
        $first = $this->createCategory('tableware');
        $second = $this->createCategory('cookware');

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', [
                'birth_date' => '1995-04-12',
                'marketing_sms' => true,
                'preferred_category_ids' => [$second, $first],
            ])
            ->assertOk()
            ->assertJsonPath('data.birth_date', '1995-04-12')
            ->assertJsonPath('data.marketing_sms', true)
            ->assertJsonPath(
                'data.preferred_category_ids',
                [$first, $second]
            );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'birth_date' => '1995-04-12',
            'marketing_sms' => 1,
        ]);

        $this->assertDatabaseHas('category_user_preferences', [
            'user_id' => $user->id,
            'category_id' => $first,
        ]);

        $this->assertDatabaseHas('category_user_preferences', [
            'user_id' => $user->id,
            'category_id' => $second,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.birth_date', '1995-04-12')
            ->assertJsonPath('data.marketing_sms', true)
            ->assertJsonPath(
                'data.preferred_category_ids',
                [$first, $second]
            );
    }

    public function test_partial_update_preserves_preferences(): void
    {
        $category = $this->createCategory('kitchen-tools');

        $user = User::factory()->create([
            'birth_date' => '1990-08-15',
            'marketing_sms' => true,
        ]);

        $user->preferredCategories()->attach($category);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', [
                'first_name' => 'Updated',
            ])
            ->assertOk()
            ->assertJsonPath('data.birth_date', '1990-08-15')
            ->assertJsonPath('data.marketing_sms', true)
            ->assertJsonPath(
                'data.preferred_category_ids',
                [$category]
            );
    }

    public function test_customer_can_clear_preferences(): void
    {
        $category = $this->createCategory('furniture');

        $user = User::factory()->create([
            'birth_date' => '1992-01-20',
            'marketing_sms' => true,
        ]);

        $user->preferredCategories()->attach($category);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', [
                'birth_date' => null,
                'marketing_sms' => false,
                'preferred_category_ids' => [],
            ])
            ->assertOk()
            ->assertJsonPath('data.birth_date', null)
            ->assertJsonPath('data.marketing_sms', false)
            ->assertJsonPath('data.preferred_category_ids', []);

        $this->assertDatabaseMissing(
            'category_user_preferences',
            [
                'user_id' => $user->id,
                'category_id' => $category,
            ]
        );
    }

    public function test_invalid_preferences_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', [
                'birth_date' => '2999-01-01',
                'marketing_sms' => 'invalid',
                'preferred_category_ids' => [999999],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'birth_date',
                'marketing_sms',
                'preferred_category_ids.0',
            ]);
    }

    public function test_inactive_and_duplicate_categories_are_rejected(): void
    {
        $active = $this->createCategory('active-category');
        $inactive = $this->createCategory(
            'inactive-category',
            false
        );

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', [
                'preferred_category_ids' => [
                    $active,
                    $active,
                    $inactive,
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'preferred_category_ids.1',
                'preferred_category_ids.2',
            ]);

        $this->assertDatabaseCount(
            'category_user_preferences',
            0
        );
    }

    public function test_preferences_are_isolated_between_users(): void
    {
        $category = $this->createCategory('private-choice');

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $firstUser->preferredCategories()->attach($category);

        $this->actingAs($secondUser, 'sanctum')
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.preferred_category_ids', []);

        $this->assertDatabaseMissing(
            'category_user_preferences',
            [
                'user_id' => $secondUser->id,
                'category_id' => $category,
            ]
        );
    }
}