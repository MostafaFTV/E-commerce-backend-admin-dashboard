<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createCategory(): Category
    {
        return Category::create([
            'name' => [
                'en' => 'Cookware',
                'fa' => 'ظروف آشپزی',
            ],

            'slug' => 'cookware-' . uniqid(),

            'description' => [
                'en' => 'Kitchen cookware',
                'fa' => 'ظروف آشپزخانه',
            ],

            'is_active' => true,

            'sort_order' => 0,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Locale Resolution
    |--------------------------------------------------------------------------
    */

    public function test_english_locale_is_resolved_correctly(): void
    {
        $this->createCategory();

        $response = $this->withHeader(
            'Accept-Language',
            'en'
        )->getJson('/api/categories');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.locale',
                'en'
            )
            ->assertJsonPath(
                'data.0.direction',
                'ltr'
            )
            ->assertJsonPath(
                'data.0.name',
                'Cookware'
            );
    }

    public function test_persian_locale_is_resolved_correctly(): void
    {
        $this->createCategory();

        $response = $this->withHeader(
            'Accept-Language',
            'fa'
        )->getJson('/api/categories');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.locale',
                'fa'
            )
            ->assertJsonPath(
                'data.0.direction',
                'rtl'
            )
            ->assertJsonPath(
                'data.0.name',
                'ظروف آشپزی'
            );
    }

    public function test_persian_iran_locale_is_normalized_to_persian(): void
    {
        $this->createCategory();

        $response = $this->withHeader(
            'Accept-Language',
            'fa-IR'
        )->getJson('/api/categories');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.locale',
                'fa'
            )
            ->assertJsonPath(
                'data.0.direction',
                'rtl'
            )
            ->assertJsonPath(
                'data.0.name',
                'ظروف آشپزی'
            );
    }

    public function test_locale_matching_is_case_insensitive(): void
    {
        $this->createCategory();

        $response = $this->withHeader(
            'Accept-Language',
            'FA-ir'
        )->getJson('/api/categories');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.locale',
                'fa'
            )
            ->assertJsonPath(
                'data.0.direction',
                'rtl'
            )
            ->assertJsonPath(
                'data.0.name',
                'ظروف آشپزی'
            );
    }

    public function test_first_supported_locale_in_accept_language_is_used(): void
    {
        $this->createCategory();

        $response = $this->withHeader(
            'Accept-Language',
            'de-DE,fa-IR;q=0.9,en;q=0.8'
        )->getJson('/api/categories');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.locale',
                'fa'
            )
            ->assertJsonPath(
                'data.0.direction',
                'rtl'
            )
            ->assertJsonPath(
                'data.0.name',
                'ظروف آشپزی'
            );
    }

    public function test_unsupported_locale_falls_back_to_application_fallback_locale(): void
    {
        $this->createCategory();

        $response = $this->withHeader(
            'Accept-Language',
            'de-DE'
        )->getJson('/api/categories');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.locale',
                'en'
            )
            ->assertJsonPath(
                'data.0.direction',
                'ltr'
            )
            ->assertJsonPath(
                'data.0.name',
                'Cookware'
            );
    }

    public function test_missing_accept_language_header_uses_fallback_locale(): void
    {
        $this->createCategory();

        $response = $this->getJson(
            '/api/categories'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.locale',
                'en'
            )
            ->assertJsonPath(
                'data.0.direction',
                'ltr'
            )
            ->assertJsonPath(
                'data.0.name',
                'Cookware'
            );
    }

    public function test_quality_values_do_not_break_locale_resolution(): void
    {
        $this->createCategory();

        $response = $this->withHeader(
            'Accept-Language',
            'en-US;q=0.8,fa-IR;q=0.9'
        )->getJson('/api/categories');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.locale',
                'en'
            )
            ->assertJsonPath(
                'data.0.direction',
                'ltr'
            )
            ->assertJsonPath(
                'data.0.name',
                'Cookware'
            );
    }

    public function test_locale_is_available_on_request_attributes(): void
    {
        $this->createCategory();

        $response = $this->withHeader(
            'Accept-Language',
            'fa'
        )->getJson('/api/categories');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.locale',
                'fa'
            )
            ->assertJsonPath(
                'data.0.direction',
                'rtl'
            );
    }
}
