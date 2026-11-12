<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreApiTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function createRole(
        string $slug,
        ?array $permissionSlugs = null
    ): Role {
        $role = Role::create([
            'name' => Str::headline($slug),
            'slug' => $slug,
        ]);

        if ($permissionSlugs !== null) {
            $permissions = collect($permissionSlugs)
                ->map(function (string $permissionSlug) {
                    return Permission::firstOrCreate(
                        [
                            'slug' => $permissionSlug,
                        ],
                        [
                            'name' => Str::headline(
                                str_replace(
                                    '.',
                                    ' ',
                                    $permissionSlug
                                )
                            ),
                        ]
                    );
                });

            $role->permissions()->sync(
                $permissions->pluck('id')
            );
        }

        return $role;
    }

    protected function createUserWithRole(
        string $roleSlug,
        ?array $permissionSlugs = null
    ): User {
        $role = Role::where(
            'slug',
            $roleSlug
        )->first();

        if (! $role) {
            $role = $this->createRole(
                $roleSlug,
                $permissionSlugs
            );
        } elseif ($permissionSlugs !== null) {
            $permissions = collect($permissionSlugs)
                ->map(function (string $permissionSlug) {
                    return Permission::firstOrCreate(
                        [
                            'slug' => $permissionSlug,
                        ],
                        [
                            'name' => Str::headline(
                                str_replace(
                                    '.',
                                    ' ',
                                    $permissionSlug
                                )
                            ),
                        ]
                    );
                });

            $role->permissions()->sync(
                $permissions->pluck('id')
            );
        }

        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);

        $user->roles()->attach(
            $role->id
        );

        return $user;
    }

    protected function createStore(
        array $attributes = []
    ): Store {
        return Store::create(
            array_merge(
                [
                    'name' => 'Tehran Store',
                    'slug' => 'tehran-store',
                    'description' => 'Main TKB store.',
                    'address' => '123 Main Street',
                    'city' => 'Tehran',
                    'phone' => '+982100000000',
                    'email' => 'tehran@tkb.com',
                    'latitude' => 35.6892000,
                    'longitude' => 51.3890000,
                    'opening_hours' => [
                        'monday' => '09:00-21:00',
                        'tuesday' => '09:00-21:00',
                    ],
                    'image' => 'stores/tehran.webp',
                    'is_active' => true,
                ],
                $attributes
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Public API
    |--------------------------------------------------------------------------
    */

    public function test_guest_can_list_only_active_stores(): void
    {
        $activeStore = $this->createStore();

        $inactiveStore = $this->createStore([
            'name' => 'Inactive Store',
            'slug' => 'inactive-store',
            'is_active' => false,
        ]);

        $response = $this->getJson(
            '/api/stores'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $activeStore->id
            )
            ->assertJsonMissing([
                'id' => $inactiveStore->id,
            ]);
    }

    public function test_public_store_list_is_paginated(): void
    {
        $this->createStore();

        $response = $this->getJson(
            '/api/stores?per_page=1'
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);

        $this->assertCount(
            1,
            $response->json('data')
        );
    }

    public function test_public_store_list_returns_expected_fields(): void
    {
        $this->createStore();

        $response = $this->getJson(
            '/api/stores'
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'address',
                        'city',
                        'phone',
                        'email',
                        'latitude',
                        'longitude',
                        'opening_hours',
                        'image',
                        'is_active',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    public function test_public_store_show_works_by_slug(): void
    {
        $store = $this->createStore();

        $response = $this->getJson(
            '/api/stores/' . $store->slug
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $store->id
            )
            ->assertJsonPath(
                'data.slug',
                $store->slug
            );
    }

    public function test_public_store_show_returns_404_for_inactive_store(): void
    {
        $store = $this->createStore([
            'is_active' => false,
        ]);

        $this->getJson(
            '/api/stores/' . $store->slug
        )->assertNotFound();
    }

    public function test_public_store_show_returns_404_for_unknown_slug(): void
    {
        $this->getJson(
            '/api/stores/does-not-exist'
        )->assertNotFound();
    }

    public function test_public_store_endpoint_does_not_require_authentication(): void
    {
        $store = $this->createStore();

        $this->getJson(
            '/api/stores/' . $store->slug
        )->assertOk();
    }

    public function test_public_store_list_returns_persian_localized_fields(): void
    {
        $this->createStore([
            'name' =>
                'تهران',

            'name_en' =>
                'Tehran',

            'description' =>
                'بوتیک مال فرشته',

            'description_en' =>
                'Fereshteh Boutique Mall',

            'address' =>
                'تهران، فرشته، بوتیک مال',

            'address_en' =>
                'Boutique Mall, Fereshteh, Tehran',

            'city' =>
                'تهران',

            'city_en' =>
                'Tehran',

            'floor_unit' =>
                'طبقه اول، واحد ۱۰',

            'floor_unit_en' =>
                '1st Floor · Unit 10',

            'opening_hours' => [
                'شنبه تا پنج‌شنبه: 08:00–20:00',
                'جمعه: تعطیل',
            ],

            'opening_hours_en' => [
                'Saturday–Thursday: 08:00–20:00',
                'Friday: Closed',
            ],
        ]);

        $response = $this->getJson(
            '/api/stores?locale=fa'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.name',
                'تهران'
            )
            ->assertJsonPath(
                'data.0.description',
                'بوتیک مال فرشته'
            )
            ->assertJsonPath(
                'data.0.address',
                'تهران، فرشته، بوتیک مال'
            )
            ->assertJsonPath(
                'data.0.city',
                'تهران'
            )
            ->assertJsonPath(
                'data.0.floor_unit',
                'طبقه اول، واحد ۱۰'
            )
            ->assertJsonPath(
                'data.0.opening_hours.0',
                'شنبه تا پنج‌شنبه: 08:00–20:00'
            );
    }

    public function test_public_store_list_returns_english_localized_fields(): void
    {
        $this->createStore([
            'name' =>
                'تهران',

            'name_en' =>
                'Tehran',

            'description' =>
                'بوتیک مال فرشته',

            'description_en' =>
                'Fereshteh Boutique Mall',

            'address' =>
                'تهران، فرشته، بوتیک مال',

            'address_en' =>
                'Boutique Mall, Fereshteh, Tehran',

            'city' =>
                'تهران',

            'city_en' =>
                'Tehran',

            'floor_unit' =>
                'طبقه اول، واحد ۱۰',

            'floor_unit_en' =>
                '1st Floor · Unit 10',

            'opening_hours' => [
                'شنبه تا پنج‌شنبه: 08:00–20:00',
            ],

            'opening_hours_en' => [
                'Saturday–Thursday: 08:00–20:00',
                'Friday: Closed',
            ],
        ]);

        $response = $this->getJson(
            '/api/stores?locale=en'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.name',
                'Tehran'
            )
            ->assertJsonPath(
                'data.0.description',
                'Fereshteh Boutique Mall'
            )
            ->assertJsonPath(
                'data.0.address',
                'Boutique Mall, Fereshteh, Tehran'
            )
            ->assertJsonPath(
                'data.0.city',
                'Tehran'
            )
            ->assertJsonPath(
                'data.0.floor_unit',
                '1st Floor · Unit 10'
            )
            ->assertJsonPath(
                'data.0.opening_hours.0',
                'Saturday–Thursday: 08:00–20:00'
            );
    }

    public function test_public_store_english_locale_falls_back_to_persian_when_translation_is_missing(): void
    {
        $this->createStore([
            'name' =>
                'نوشهر',

            'name_en' =>
                null,

            'description' =>
                'هتل اراز نوشهر',

            'description_en' =>
                null,

            'address' =>
                'نوشهر، هتل اراز نوشهر',

            'address_en' =>
                null,

            'city' =>
                'نوشهر',

            'city_en' =>
                null,

            'floor_unit' =>
                'طبقه سوم، واحد ۳۰',

            'floor_unit_en' =>
                null,

            'opening_hours' => [
                'جمعه: تعطیل',
            ],

            'opening_hours_en' =>
                null,
        ]);

        $response = $this->getJson(
            '/api/stores?locale=en'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.name',
                'نوشهر'
            )
            ->assertJsonPath(
                'data.0.description',
                'هتل اراز نوشهر'
            )
            ->assertJsonPath(
                'data.0.address',
                'نوشهر، هتل اراز نوشهر'
            )
            ->assertJsonPath(
                'data.0.city',
                'نوشهر'
            )
            ->assertJsonPath(
                'data.0.floor_unit',
                'طبقه سوم، واحد ۳۰'
            )
            ->assertJsonPath(
                'data.0.opening_hours.0',
                'جمعه: تعطیل'
            );
    }

    public function test_public_store_show_supports_english_locale(): void
    {
        $store = $this->createStore([
            'name' =>
                'تهران',

            'name_en' =>
                'Tehran',

            'address' =>
                'تهران، فرشته',

            'address_en' =>
                'Fereshteh, Tehran',

            'city' =>
                'تهران',

            'city_en' =>
                'Tehran',

            'floor_unit' =>
                'طبقه اول',

            'floor_unit_en' =>
                '1st Floor',
        ]);

        $this->getJson(
            '/api/stores/' .
            $store->slug .
            '?locale=en'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Tehran'
            )
            ->assertJsonPath(
                'data.address',
                'Fereshteh, Tehran'
            )
            ->assertJsonPath(
                'data.floor_unit',
                '1st Floor'
            );
    }

    public function test_public_store_list_is_ordered_by_sort_order_then_id(): void
    {
        $later = $this->createStore([
            'name' =>
                'Later Store',

            'slug' =>
                'later-store',

            'sort_order' =>
                20,
        ]);

        $first = $this->createStore([
            'name' =>
                'First Store',

            'slug' =>
                'first-store',

            'sort_order' =>
                5,
        ]);

        $second = $this->createStore([
            'name' =>
                'Second Store',

            'slug' =>
                'second-store',

            'sort_order' =>
                5,
        ]);

        $response = $this->getJson(
            '/api/stores'
        );

        $response->assertOk();

        $this->assertSame(
            [
                $first->id,
                $second->id,
                $later->id,
            ],
            collect(
                $response->json('data')
            )
                ->pluck('id')
                ->all()
        );
    }

    public function test_public_store_list_rejects_invalid_locale(): void
    {
        $this->getJson(
            '/api/stores?locale=de'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'locale',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Index / Show
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_list_stores_with_view_permission(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.view',
            ]
        );

        $this->createStore();

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/admin/stores'
        )
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);
    }

    public function test_admin_store_list_supports_search(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.view',
            ]
        );

        $this->createStore([
            'name' => 'Tehran Boutique',
            'slug' => 'tehran-boutique',
            'city' => 'Tehran',
        ]);

        $this->createStore([
            'name' => 'Paris Boutique',
            'slug' => 'paris-boutique',
            'city' => 'Paris',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/admin/stores?search=Paris'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.name',
                'Paris Boutique'
            );

        $this->assertCount(
            1,
            $response->json('data')
        );
    }

    public function test_admin_store_list_supports_active_filter(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.view',
            ]
        );

        $this->createStore();

        $this->createStore([
            'name' => 'Inactive Store',
            'slug' => 'inactive-store',
            'is_active' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/admin/stores?is_active=0'
        );

        $response->assertOk();

        $data = $response->json('data');

        $this->assertCount(
            1,
            $data
        );

        $this->assertFalse(
            $data[0]['is_active']
        );
    }

    public function test_admin_can_show_store_with_view_permission(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.view',
            ]
        );

        $store = $this->createStore();

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/admin/stores/' . $store->id
        )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $store->id
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Create
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_create_store(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.create',
            ]
        );

        Sanctum::actingAs($user);

        $payload = [
            'name' => 'Paris Boutique',
            'description' => 'Paris TKB store.',
            'address' => '10 Rue de Paris',
            'city' => 'Paris',
            'phone' => '+33100000000',
            'email' => 'paris@tkb.com',
            'latitude' => 48.8566000,
            'longitude' => 2.3522000,
            'opening_hours' => [
                'monday' => '09:00-21:00',
                'tuesday' => '09:00-21:00',
            ],
            'image' => 'stores/paris.webp',
            'is_active' => true,
        ];

        $response = $this->postJson(
            '/api/admin/stores',
            $payload
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Paris Boutique'
            )
            ->assertJsonPath(
                'data.slug',
                'paris-boutique'
            );

        $this->assertDatabaseHas(
            'stores',
            [
                'name' => 'Paris Boutique',
                'slug' => 'paris-boutique',
                'city' => 'Paris',
                'is_active' => 1,
            ]
        );
    }

    public function test_admin_can_create_store_with_explicit_slug(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.create',
            ]
        );

        Sanctum::actingAs($user);

        $response = $this->postJson(
            '/api/admin/stores',
            [
                'name' => 'Tehran Flagship',
                'slug' => 'tehran-flagship',
                'address' => '1 Flagship Street',
                'city' => 'Tehran',
                'is_active' => true,
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.slug',
                'tehran-flagship'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Update
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_update_store(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.update',
            ]
        );

        $store = $this->createStore();

        Sanctum::actingAs($user);

        $response = $this->putJson(
            '/api/admin/stores/' . $store->id,
            [
                'name' => 'Updated Store',
                'city' => 'Paris',
                'is_active' => false,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Updated Store'
            )
            ->assertJsonPath(
                'data.city',
                'Paris'
            )
            ->assertJsonPath(
                'data.is_active',
                false
            );

        $this->assertDatabaseHas(
            'stores',
            [
                'id' => $store->id,
                'name' => 'Updated Store',
                'city' => 'Paris',
                'is_active' => 0,
            ]
        );
    }

    public function test_admin_can_partially_update_store(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.update',
            ]
        );

        $store = $this->createStore();

        Sanctum::actingAs($user);

        $this->putJson(
            '/api/admin/stores/' . $store->id,
            [
                'phone' => '+33111111111',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.phone',
                '+33111111111'
            );

        $store->refresh();

        $this->assertSame(
            'Tehran Store',
            $store->name
        );
    }

    public function test_admin_can_create_store_with_localized_fields(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.create',
            ]
        );

        Sanctum::actingAs(
            $user
        );

        $response = $this->postJson(
            '/api/admin/stores',
            [
                'name' =>
                    'تهران',

                'name_en' =>
                    'Tehran',

                'slug' =>
                    'tehran',

                'description' =>
                    'بوتیک مال فرشته',

                'description_en' =>
                    'Fereshteh Boutique Mall',

                'address' =>
                    'تهران، فرشته، بوتیک مال',

                'address_en' =>
                    'Boutique Mall, Fereshteh, Tehran',

                'city' =>
                    'تهران',

                'city_en' =>
                    'Tehran',

                'floor_unit' =>
                    'طبقه اول، واحد ۱۰',

                'floor_unit_en' =>
                    '1st Floor · Unit 10',

                'opening_hours' => [
                    'شنبه تا پنج‌شنبه: 08:00–20:00',
                    'جمعه: تعطیل',
                ],

                'opening_hours_en' => [
                    'Saturday–Thursday: 08:00–20:00',
                    'Friday: Closed',
                ],

                'latitude' =>
                    35.7911173,

                'longitude' =>
                    51.4207571,

                'is_active' =>
                    true,

                'sort_order' =>
                    10,
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'تهران'
            )
            ->assertJsonPath(
                'data.name_en',
                'Tehran'
            )
            ->assertJsonPath(
                'data.floor_unit',
                'طبقه اول، واحد ۱۰'
            )
            ->assertJsonPath(
                'data.floor_unit_en',
                '1st Floor · Unit 10'
            )
            ->assertJsonPath(
                'data.sort_order',
                10
            );

        $store = Store::query()
            ->where(
                'slug',
                'tehran'
            )
            ->firstOrFail();

        $this->assertSame(
            'Tehran',
            $store->name_en
        );

        $this->assertSame(
            '1st Floor · Unit 10',
            $store->floor_unit_en
        );

        $this->assertSame(
            [
                'Saturday–Thursday: 08:00–20:00',
                'Friday: Closed',
            ],
            $store->opening_hours_en
        );

        $this->assertSame(
            10,
            $store->sort_order
        );
    }

    public function test_admin_can_update_localized_store_fields(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.update',
            ]
        );

        $store = $this->createStore([
            'name' =>
                'تهران',

            'name_en' =>
                'Tehran',

            'floor_unit' =>
                'طبقه اول',

            'floor_unit_en' =>
                '1st Floor',

            'sort_order' =>
                10,
        ]);

        Sanctum::actingAs(
            $user
        );

        $response = $this->putJson(
            '/api/admin/stores/' .
            $store->id,
            [
                'slug' =>
                    $store->slug,

                'name_en' =>
                    'Tehran Flagship',

                'description_en' =>
                    'TKB Tehran flagship store.',

                'address_en' =>
                    'Boutique Mall, Fereshteh, Tehran',

                'city_en' =>
                    'Tehran',

                'floor_unit_en' =>
                    '1st Floor · Unit 10',

                'opening_hours_en' => [
                    'Saturday–Thursday: 10:00–22:00',
                    'Friday: Closed',
                ],

                'sort_order' =>
                    3,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.name_en',
                'Tehran Flagship'
            )
            ->assertJsonPath(
                'data.floor_unit_en',
                '1st Floor · Unit 10'
            )
            ->assertJsonPath(
                'data.sort_order',
                3
            );

        $store->refresh();

        $this->assertSame(
            'Tehran Flagship',
            $store->name_en
        );

        $this->assertSame(
            'Boutique Mall, Fereshteh, Tehran',
            $store->address_en
        );

        $this->assertSame(
            [
                'Saturday–Thursday: 10:00–22:00',
                'Friday: Closed',
            ],
            $store->opening_hours_en
        );

        $this->assertSame(
            3,
            $store->sort_order
        );
    }

    public function test_admin_store_search_supports_english_fields(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.view',
            ]
        );

        $expected = $this->createStore([
            'name' =>
                'شعبه تهران',

            'name_en' =>
                'Tehran Flagship',

            'slug' =>
                'tehran-flagship',
        ]);

        $this->createStore([
            'name' =>
                'شعبه نوشهر',

            'name_en' =>
                'Nowshahr Boutique',

            'slug' =>
                'nowshahr-boutique',
        ]);

        Sanctum::actingAs(
            $user
        );

        $response = $this->getJson(
            '/api/admin/stores?search=Flagship'
        );

        $response->assertOk();

        $this->assertSame(
            [
                $expected->id,
            ],
            collect(
                $response->json('data')
            )
                ->pluck('id')
                ->all()
        );
    }

    public function test_store_creation_rejects_invalid_english_opening_hours(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.create',
            ]
        );

        Sanctum::actingAs(
            $user
        );

        $this->postJson(
            '/api/admin/stores',
            [
                'name' =>
                    'Test Store',

                'address' =>
                    'Test Address',

                'city' =>
                    'Tehran',

                'opening_hours_en' =>
                    '09:00-21:00',

                'is_active' =>
                    true,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'opening_hours_en',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Delete
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_delete_store(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.delete',
            ]
        );

        $store = $this->createStore();

        Sanctum::actingAs($user);

        $this->deleteJson(
            '/api/admin/stores/' . $store->id
        )
            ->assertOk()
            ->assertJson([
                'message' => 'Store deleted successfully.',
                'deleted' => true,
            ]);

        $this->assertDatabaseMissing(
            'stores',
            [
                'id' => $store->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Activity Logging
    |--------------------------------------------------------------------------
    */

    public function test_creating_store_creates_activity_log(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.create',
            ]
        );

        Sanctum::actingAs($user);

        $response = $this->postJson(
            '/api/admin/stores',
            [
                'name' => 'Paris Boutique',
                'address' => '10 Rue de Paris',
                'city' => 'Paris',
                'email' => 'paris@tkb.com',
                'is_active' => true,
            ]
        );

        $response->assertCreated();

        $storeId = $response->json(
            'data.id'
        );

        $log = ActivityLog::query()
            ->where(
                'action',
                'stores.created'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $user->id,
            $log->user_id
        );

        $this->assertSame(
            'stores.created',
            $log->action
        );

        $this->assertSame(
            'Store created.',
            $log->description
        );

        $this->assertSame(
            Store::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $storeId,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            'Paris Boutique',
            $log->properties['store']['name']
        );

        $this->assertSame(
            'paris-boutique',
            $log->properties['store']['slug']
        );
    }

    public function test_updating_store_creates_activity_log_with_changed_fields(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.update',
            ]
        );

        $store = $this->createStore();

        Sanctum::actingAs($user);

        $this->putJson(
            '/api/admin/stores/' . $store->id,
            [
                'name' => 'Updated Store',
                'city' => 'Paris',
                'is_active' => false,
            ]
        )
            ->assertOk();

        $log = ActivityLog::query()
            ->where(
                'action',
                'stores.updated'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $user->id,
            $log->user_id
        );

        $this->assertSame(
            Store::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $store->id,
            $log->properties['subject']['id']
        );

        $changedFields = $log->properties['changed_fields'];

        $this->assertContains(
            'name',
            $changedFields
        );

        $this->assertContains(
            'city',
            $changedFields
        );

        $this->assertContains(
            'is_active',
            $changedFields
        );

        $this->assertSame(
            'Updated Store',
            $log->properties['changes']['name']
        );

        $this->assertSame(
            'Paris',
            $log->properties['changes']['city']
        );

        $this->assertFalse(
            $log->properties['changes']['is_active']
        );
    }

    public function test_deleting_store_creates_activity_log(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.delete',
            ]
        );

        $store = $this->createStore();

        Sanctum::actingAs($user);

        $this->deleteJson(
            '/api/admin/stores/' . $store->id
        )
            ->assertOk();

        $log = ActivityLog::query()
            ->where(
                'action',
                'stores.deleted'
            )
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        $this->assertSame(
            $user->id,
            $log->user_id
        );

        $this->assertSame(
            'stores.deleted',
            $log->action
        );

        $this->assertSame(
            Store::class,
            $log->properties['subject']['type']
        );

        $this->assertSame(
            $store->id,
            $log->properties['subject']['id']
        );

        $this->assertSame(
            'Tehran Store',
            $log->properties['store']['name']
        );

        $this->assertSame(
            'tehran-store',
            $log->properties['store']['slug']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public function test_store_creation_requires_required_fields(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.create',
            ]
        );

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/admin/stores',
            []
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'address',
                'city',
                'is_active',
            ]);
    }

    public function test_store_creation_rejects_invalid_slug(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.create',
            ]
        );

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/admin/stores',
            [
                'name' => 'Invalid Slug Store',
                'slug' => 'Invalid Slug!',
                'address' => 'Test Address',
                'city' => 'Tehran',
                'is_active' => true,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }

    public function test_store_creation_rejects_duplicate_slug(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.create',
            ]
        );

        $this->createStore();

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/admin/stores',
            [
                'name' => 'Another Store',
                'slug' => 'tehran-store',
                'address' => 'Test Address',
                'city' => 'Tehran',
                'is_active' => true,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }

    public function test_store_update_allows_existing_slug_of_current_store(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.update',
            ]
        );

        $store = $this->createStore();

        Sanctum::actingAs($user);

        $this->putJson(
            '/api/admin/stores/' . $store->id,
            [
                'slug' => $store->slug,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.slug',
                $store->slug
            );
    }

    public function test_store_creation_rejects_invalid_email(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.create',
            ]
        );

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/admin/stores',
            [
                'name' => 'Invalid Email Store',
                'address' => 'Test Address',
                'city' => 'Tehran',
                'email' => 'not-an-email',
                'is_active' => true,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);
    }

    public function test_store_creation_rejects_invalid_coordinates(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.create',
            ]
        );

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/admin/stores',
            [
                'name' => 'Invalid Coordinates Store',
                'address' => 'Test Address',
                'city' => 'Tehran',
                'latitude' => 100,
                'longitude' => 200,
                'is_active' => true,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'latitude',
                'longitude',
            ]);
    }

    public function test_store_creation_rejects_invalid_opening_hours(): void
    {
        $user = $this->createUserWithRole(
            'admin',
            [
                'stores.create',
            ]
        );

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/admin/stores',
            [
                'name' => 'Invalid Hours Store',
                'address' => 'Test Address',
                'city' => 'Tehran',
                'opening_hours' => '09:00-21:00',
                'is_active' => true,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'opening_hours',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication / Authorization
    |--------------------------------------------------------------------------
    */

    public function test_guest_cannot_access_admin_store_list(): void
    {
        $this->getJson(
            '/api/admin/stores'
        )->assertUnauthorized();
    }

    public function test_admin_without_view_permission_cannot_list_stores(): void
    {
        $user = $this->createUserWithRole(
            'admin'
        );

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/admin/stores'
        )->assertForbidden();
    }

    public function test_admin_without_create_permission_cannot_create_store(): void
    {
        $user = $this->createUserWithRole(
            'admin'
        );

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/admin/stores',
            [
                'name' => 'Unauthorized Store',
                'address' => 'Test Address',
                'city' => 'Tehran',
                'is_active' => true,
            ]
        )->assertForbidden();
    }

    public function test_admin_without_update_permission_cannot_update_store(): void
    {
        $user = $this->createUserWithRole(
            'admin'
        );

        $store = $this->createStore();

        Sanctum::actingAs($user);

        $this->putJson(
            '/api/admin/stores/' . $store->id,
            [
                'name' => 'Unauthorized Update',
            ]
        )->assertForbidden();
    }

    public function test_admin_without_delete_permission_cannot_delete_store(): void
    {
        $user = $this->createUserWithRole(
            'admin'
        );

        $store = $this->createStore();

        Sanctum::actingAs($user);

        $this->deleteJson(
            '/api/admin/stores/' . $store->id
        )->assertForbidden();
    }

    public function test_accountant_cannot_access_store_management(): void
    {
        $user = $this->createUserWithRole(
            'accountant'
        );

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/admin/stores'
        )->assertForbidden();
    }

    public function test_support_cannot_access_store_management(): void
    {
        $user = $this->createUserWithRole(
            'support'
        );

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/admin/stores'
        )->assertForbidden();
    }

    public function test_customer_cannot_access_store_management(): void
    {
        $user = $this->createUserWithRole(
            'customer'
        );

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/admin/stores'
        )->assertForbidden();
    }

    public function test_super_admin_can_access_store_management(): void
    {
        $allPermissions = [
            'stores.view',
            'stores.create',
            'stores.update',
            'stores.delete',
        ];

        $user = $this->createUserWithRole(
            'super_admin',
            $allPermissions
        );

        Sanctum::actingAs($user);

        $this->createStore();

        $this->getJson(
            '/api/admin/stores'
        )->assertOk();
    }
}
