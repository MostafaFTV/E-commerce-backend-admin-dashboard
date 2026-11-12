<?php

namespace App\Support;

use App\Models\Product;

final class ProductSearchIndexer
{
    /**
     * @return array{
     *     search_text: string,
     *     search_compact: string
     * }
     */
    public static function build(
        Product $product
    ): array {
        $source = implode(
            ' ',
            self::candidates(
                $product
            )
        );

        return [
            'search_text' =>
                SearchTextNormalizer::normalize(
                    $source
                ),

            'search_compact' =>
                SearchTextNormalizer::compact(
                    $source
                ),
        ];
    }

    /**
     * Flat searchable values used by fuzzy matching
     * and the persisted search index.
     *
     * @return array<int, string>
     */
    public static function candidates(
        Product $product
    ): array {
        $groups =
            self::candidateGroups(
                $product
            );

        return array_values(
            array_merge(
                $groups['names'],
                $groups['brand'],
                $groups['slug']
            )
        );
    }

    /**
     * Keep searchable fields separated so relevance
     * ranking can weight names, brands and slugs
     * differently.
     *
     * @return array{
     *     names: array<int, string>,
     *     brand: array<int, string>,
     *     slug: array<int, string>
     * }
     */
    public static function candidateGroups(
        Product $product
    ): array {
        $attributes =
            $product->getAttributes();

        $translations =
            self::decodeTranslations(
                $attributes['name'] ?? null
            );

        return [
            'names' =>
                self::cleanValues([
                    $translations['en'] ?? null,
                    $translations['fa'] ?? null,
                ]),

            'brand' =>
                self::cleanValues([
                    $attributes['brand'] ?? null,
                ]),

            'slug' =>
                self::cleanValues([
                    $attributes['slug'] ?? null,
                ]),
        ];
    }

    /**
     * @param array<int, mixed> $values
     *
     * @return array<int, string>
     */
    private static function cleanValues(
        array $values
    ): array {
        return array_values(
            array_filter(
                array_map(
                    static fn (
                        mixed $value
                    ): string =>
                        trim(
                            (string) $value
                        ),
                    $values
                ),
                static fn (
                    string $value
                ): bool =>
                    $value !== ''
            )
        );
    }

    /**
     * @return array<string, string|null>
     */
    private static function decodeTranslations(
        mixed $value
    ): array {
        if (is_array($value)) {
            return $value;
        }

        if (
            !is_string($value) ||
            trim($value) === ''
        ) {
            return [];
        }

        $decoded = json_decode(
            $value,
            true
        );

        return is_array($decoded)
            ? $decoded
            : [];
    }
}