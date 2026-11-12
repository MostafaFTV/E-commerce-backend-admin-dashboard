<?php

namespace Tests\Unit;

use App\Casts\LocalizedText;
use App\Enums\Locale;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class LocalizedTextCastTest extends TestCase
{
    public function test_english_translation_is_returned_for_english_locale(): void
    {
        app()->setLocale(Locale::EN->value);

        $model = $this->makeModel(
            json_encode([
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ], JSON_UNESCAPED_UNICODE)
        );

        $this->assertSame(
            'Cookware',
            $model->name
        );
    }

    public function test_persian_translation_is_returned_for_persian_locale(): void
    {
        app()->setLocale(Locale::FA->value);

        $model = $this->makeModel(
            json_encode([
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ], JSON_UNESCAPED_UNICODE)
        );

        $this->assertSame(
            'ظروف پخت‌وپز',
            $model->name
        );
    }

    public function test_missing_current_locale_falls_back_to_fallback_locale(): void
    {
        app()->setLocale(Locale::FA->value);

        $model = $this->makeModel(
            json_encode([
                'en' => 'Cookware',
            ], JSON_UNESCAPED_UNICODE)
        );

        $this->assertSame(
            'Cookware',
            $model->name
        );
    }

    public function test_missing_all_supported_locales_uses_first_available_translation(): void
    {
        app()->setLocale(Locale::FA->value);

        $model = $this->makeModel(
            json_encode([
                'en-US' => 'Should be ignored',
                'en' => 'Cookware',
            ], JSON_UNESCAPED_UNICODE)
        );

        $this->assertSame(
            'Cookware',
            $model->name
        );
    }

    public function test_setting_string_updates_only_current_locale(): void
    {
        app()->setLocale(Locale::FA->value);

        $model = $this->makeModel(
            json_encode([
                'en' => 'Cookware',
                'fa' => 'قدیمی',
            ], JSON_UNESCAPED_UNICODE)
        );

        $model->name = 'ظروف پخت‌وپز';

        $raw = $model->getAttributes()['name'];

        $translations = json_decode(
            $raw,
            true
        );

        $this->assertSame(
            'Cookware',
            $translations['en']
        );

        $this->assertSame(
            'ظروف پخت‌وپز',
            $translations['fa']
        );
    }

    public function test_setting_translation_array_replaces_translation_map(): void
    {
        $model = $this->makeModel();

        $model->name = [
            'en' => 'Cookware',
            'fa' => 'ظروف پخت‌وپز',
        ];

        $translations = json_decode(
            $model->getAttributes()['name'],
            true
        );

        $this->assertSame(
            [
                'en' => 'Cookware',
                'fa' => 'ظروف پخت‌وپز',
            ],
            $translations
        );
    }

    public function test_unsupported_locale_keys_are_ignored(): void
    {
        $model = $this->makeModel();

        $model->name = [
            'en' => 'Cookware',
            'fa' => 'ظروف پخت‌وپز',
            'de' => 'Kochgeschirr',
        ];

        $translations = json_decode(
            $model->getAttributes()['name'],
            true
        );

        $this->assertArrayHasKey(
            'en',
            $translations
        );

        $this->assertArrayHasKey(
            'fa',
            $translations
        );

        $this->assertArrayNotHasKey(
            'de',
            $translations
        );
    }

    public function test_null_value_returns_null(): void
    {
        $model = $this->makeModel();

        $model->name = null;

        $this->assertNull(
            $model->getAttributes()['name']
        );
    }

    private function makeModel(
        ?string $rawName = null
    ): Model {
        $model = new class extends Model {
            protected $guarded = [];

            protected function casts(): array
            {
                return [
                    'name' => LocalizedText::class,
                ];
            }
        };

        if ($rawName !== null) {
            $model->setRawAttributes([
                'name' => $rawName,
            ]);
        }

        return $model;
    }
}
