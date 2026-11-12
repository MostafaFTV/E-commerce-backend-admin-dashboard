<?php

namespace App\Casts;

use App\Enums\Locale;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class LocalizedText implements CastsAttributes
{
    /**
     * Get the localized value from its stored JSON representation.
     */
    public function get(
        Model $model,
        string $key,
        mixed $value,
        array $attributes
    ): ?string {
        $translations = $this->decode($value);

        if ($translations === []) {
            return null;
        }

        $currentLocale = app()->getLocale();

        $fallbackLocale = config(
            'app.fallback_locale',
            Locale::EN->value
        );

        if (
            isset($translations[$currentLocale]) &&
            filled($translations[$currentLocale])
        ) {
            return (string) $translations[$currentLocale];
        }

        if (
            isset($translations[$fallbackLocale]) &&
            filled($translations[$fallbackLocale])
        ) {
            return (string) $translations[$fallbackLocale];
        }

        foreach ($translations as $translation) {
            if (filled($translation)) {
                return (string) $translation;
            }
        }

        return null;
    }

    /**
     * Store a localized value in JSON format.
     *
     * A string updates only the current locale.
     * An array replaces/sets the complete translation map.
     */
    public function set(
        Model $model,
        string $key,
        mixed $value,
        array $attributes
    ): ?string {
        if (is_array($value)) {
            return $this->encode(
                $this->normalizeTranslations($value)
            );
        }

        if ($value === null) {
            return null;
        }

        $translations = $this->decode(
            $attributes[$key] ?? null
        );

        $locale = Locale::tryFrom(
            app()->getLocale()
        );

        if (!$locale) {
            $locale = Locale::EN;
        }

        $translations[$locale->value] = (string) $value;

        return $this->encode(
            $translations
        );
    }

    /**
     * Decode the stored JSON value.
     */
    protected function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $this->normalizeTranslations($value);
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode(
            $value,
            true
        );

        if (!is_array($decoded)) {
            return [];
        }

        return $this->normalizeTranslations(
            $decoded
        );
    }

    /**
     * Normalize translation keys and values.
     */
    protected function normalizeTranslations(
        array $translations
    ): array {
        $normalized = [];

        foreach ($translations as $locale => $translation) {
            $locale = strtolower(
                trim((string) $locale)
            );

            if (!in_array(
                $locale,
                config(
                    'app.supported_locales',
                    ['en', 'fa']
                ),
                true
            )) {
                continue;
            }

            if ($translation === null) {
                $normalized[$locale] = null;

                continue;
            }

            $normalized[$locale] = (string) $translation;
        }

        return $normalized;
    }

    /**
     * Encode translations as JSON without escaping Unicode characters.
     */
    protected function encode(array $translations): string
    {
        return json_encode(
            $translations,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        );
    }
}
