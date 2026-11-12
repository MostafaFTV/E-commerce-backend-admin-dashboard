<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use App\Models\Collection;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionSeoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions): User
    {
        $admin = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $role = Role::create([
            'name' => 'SEO Test Admin',
            'slug' => 'seo-test-admin-' . uniqid(),
            'description' => 'SEO test role',
        ]);

        foreach ($permissions as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $slug,
                    'description' => 'SEO test permission',
                ]
            );

            $role->permissions()->syncWithoutDetaching([
                $permission->id,
            ]);
        }

        $admin->roles()->attach($role->id);

        return $admin;
    }

    private function collection(
        array $attributes = []
    ): Collection {
        return Collection::create(array_merge([
            'name' => [
                'en' => 'Luxury Collection',
                'fa' => 'کالکشن لوکس',
            ],
            'slug' => 'seo-collection-' . uniqid(),
            'description' => [
                'en' => 'Original collection description',
                'fa' => 'توضیحات اصلی کالکشن',
            ],
            'image' => null,
            'is_active' => true,
            'sort_order' => 1,
        ], $attributes));
    }

    private function seoCollection(
        array $attributes = []
    ): Collection {
        return $this->collection(array_merge([
            'seo_title' => [
                'en' => 'Luxury Collection SEO',
                'fa' => 'عنوان سئو کالکشن لوکس',
            ],
            'seo_description' => [
                'en' => 'English collection SEO description',
                'fa' => 'توضیحات فارسی سئو کالکشن',
            ],
            'seo_image' => '/images/seo/collection.webp',
        ], $attributes));
    }

    private function translations(
        Collection $collection,
        string $field
    ): ?array {
        $collection->refresh();

        $raw = $collection->getRawOriginal($field);

        return $raw === null
            ? null
            : json_decode($raw, true);
    }

    public function test_admin_can_create_bilingual_collection_seo(): void
    {
        $admin = $this->admin(['collections.create']);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/collections', [
                'name' => [
                    'en' => 'Premium Tableware',
                    'fa' => 'ظروف پذیرایی ممتاز',
                ],
                'slug' => 'premium-tableware-seo',
                'is_active' => true,
                'seo_title' => [
                    'en' => 'Premium Tableware SEO',
                    'fa' => 'سئو ظروف پذیرایی ممتاز',
                ],
                'seo_description' => [
                    'en' => 'Discover premium tableware',
                    'fa' => 'خرید ظروف پذیرایی ممتاز',
                ],
                'seo_image' => '/images/seo/tableware.webp',
            ]);

        $response->assertCreated();

        $collection = Collection::where(
            'slug',
            'premium-tableware-seo'
        )->firstOrFail();

        $this->assertSame(
            [
                'en' => 'Premium Tableware SEO',
                'fa' => 'سئو ظروف پذیرایی ممتاز',
            ],
            $this->translations($collection, 'seo_title')
        );

        $this->assertSame(
            [
                'en' => 'Discover premium tableware',
                'fa' => 'خرید ظروف پذیرایی ممتاز',
            ],
            $this->translations($collection, 'seo_description')
        );

        $this->assertSame(
            '/images/seo/tableware.webp',
            $collection->refresh()->seo_image
        );
    }

    public function test_persian_update_preserves_english_seo(): void
    {
        $admin = $this->admin(['collections.update']);
        $collection = $this->seoCollection();

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/admin/collections/{$collection->id}",
                [
                    'seo_title' => [
                        'fa' => 'عنوان فارسی جدید',
                    ],
                    'seo_description' => [
                        'fa' => 'توضیحات فارسی جدید',
                    ],
                ]
            )
            ->assertOk();

        $this->assertSame(
            [
                'en' => 'Luxury Collection SEO',
                'fa' => 'عنوان فارسی جدید',
            ],
            $this->translations($collection, 'seo_title')
        );

        $this->assertSame(
            [
                'en' => 'English collection SEO description',
                'fa' => 'توضیحات فارسی جدید',
            ],
            $this->translations($collection, 'seo_description')
        );
    }

    public function test_english_update_preserves_persian_seo(): void
    {
        $admin = $this->admin(['collections.update']);
        $collection = $this->seoCollection();

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/admin/collections/{$collection->id}",
                [
                    'seo_title' => [
                        'en' => 'Updated English SEO',
                    ],
                ]
            )
            ->assertOk();

        $this->assertSame(
            [
                'en' => 'Updated English SEO',
                'fa' => 'عنوان سئو کالکشن لوکس',
            ],
            $this->translations($collection, 'seo_title')
        );
    }

    public function test_admin_can_clear_all_seo_fields(): void
    {
        $admin = $this->admin(['collections.update']);
        $collection = $this->seoCollection();

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/admin/collections/{$collection->id}",
                [
                    'seo_title' => null,
                    'seo_description' => null,
                    'seo_image' => null,
                ]
            )
            ->assertOk();

        $collection->refresh();

        $this->assertNull(
            $collection->getRawOriginal('seo_title')
        );

        $this->assertNull(
            $collection->getRawOriginal('seo_description')
        );

        $this->assertNull($collection->seo_image);
    }

    public function test_omitted_seo_fields_remain_unchanged(): void
    {
        $admin = $this->admin(['collections.update']);
        $collection = $this->seoCollection();

        $originalTitle = $this->translations(
            $collection,
            'seo_title'
        );

        $originalDescription = $this->translations(
            $collection,
            'seo_description'
        );

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/admin/collections/{$collection->id}",
                [
                    'sort_order' => 7,
                ]
            )
            ->assertOk();

        $this->assertSame(
            $originalTitle,
            $this->translations($collection, 'seo_title')
        );

        $this->assertSame(
            $originalDescription,
            $this->translations($collection, 'seo_description')
        );

        $this->assertSame(
            '/images/seo/collection.webp',
            $collection->refresh()->seo_image
        );

        $this->assertSame(7, $collection->sort_order);
    }

    public function test_invalid_seo_payload_is_rejected(): void
    {
        $admin = $this->admin(['collections.create']);

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/collections', [
                'name' => [
                    'en' => 'Invalid SEO Collection',
                    'fa' => 'کالکشن نامعتبر',
                ],
                'slug' => 'invalid-collection-seo',
                'is_active' => true,
                'seo_title' => [
                    'de' => 'Unsupported language',
                ],
                'seo_description' => [
                    'en' => str_repeat('X', 1001),
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'seo_title',
                'seo_description.en',
            ]);

        $this->assertDatabaseMissing('collections', [
            'slug' => 'invalid-collection-seo',
        ]);
    }

    public function test_public_api_returns_localized_seo(): void
    {
        $collection = $this->seoCollection();

        $this
            ->withHeader('Accept-Language', 'en')
            ->getJson("/api/collections/{$collection->slug}")
            ->assertOk()
            ->assertJsonPath(
                'data.seo_title',
                'Luxury Collection SEO'
            )
            ->assertJsonPath(
                'data.seo_description',
                'English collection SEO description'
            )
            ->assertJsonPath(
                'data.seo_image',
                '/images/seo/collection.webp'
            );

        $this
            ->withHeader('Accept-Language', 'fa')
            ->getJson("/api/collections/{$collection->slug}")
            ->assertOk()
            ->assertJsonPath(
                'data.seo_title',
                'عنوان سئو کالکشن لوکس'
            )
            ->assertJsonPath(
                'data.seo_description',
                'توضیحات فارسی سئو کالکشن'
            );
    }

    public function test_admin_can_read_all_seo_translations(): void
    {
        $admin = $this->admin(['collections.view']);
        $collection = $this->seoCollection();

        $this
            ->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/collections/{$collection->id}")
            ->assertOk()
            ->assertJsonPath(
                'data.seo_title_translations.en',
                'Luxury Collection SEO'
            )
            ->assertJsonPath(
                'data.seo_title_translations.fa',
                'عنوان سئو کالکشن لوکس'
            )
            ->assertJsonPath(
                'data.seo_description_translations.en',
                'English collection SEO description'
            )
            ->assertJsonPath(
                'data.seo_description_translations.fa',
                'توضیحات فارسی سئو کالکشن'
            );
    }

    public function test_user_without_update_permission_cannot_change_seo(): void
    {
        $admin = $this->admin(['collections.view']);
        $collection = $this->seoCollection();

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/admin/collections/{$collection->id}",
                [
                    'seo_title' => [
                        'en' => 'Unauthorized Change',
                    ],
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            'Luxury Collection SEO',
            $this->translations($collection, 'seo_title')['en']
        );
    }

    public function test_seo_update_preserves_collection_and_products(): void
    {
        $admin = $this->admin(['collections.update']);

        $collection = $this->seoCollection([
            'slug' => 'stable-collection-seo',
            'sort_order' => 4,
        ]);

        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        $collection->products()->attach($product->id);

        $this
            ->actingAs($admin, 'sanctum')
            ->putJson(
                "/api/admin/collections/{$collection->id}",
                [
                    'seo_title' => [
                        'fa' => 'عنوان بهینه‌شده',
                    ],
                ]
            )
            ->assertOk();

        $collection->refresh();

        $this->assertSame(
            'stable-collection-seo',
            $collection->slug
        );

        $this->assertSame(4, $collection->sort_order);

        $this->assertSame(
            'Luxury Collection',
            json_decode(
                $collection->getRawOriginal('name'),
                true
            )['en']
        );

        $this->assertTrue(
            $collection->products()
                ->whereKey($product->id)
                ->exists()
        );
    }
}