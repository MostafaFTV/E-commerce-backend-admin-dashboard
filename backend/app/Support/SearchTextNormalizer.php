<?php

namespace App\Support;

final class SearchTextNormalizer
{
    private const CHARACTER_MAP = [
        'ي' => 'ی',
        'ى' => 'ی',
        'ك' => 'ک',

        '٠' => '0',
        '١' => '1',
        '٢' => '2',
        '٣' => '3',
        '٤' => '4',
        '٥' => '5',
        '٦' => '6',
        '٧' => '7',
        '٨' => '8',
        '٩' => '9',

        '۰' => '0',
        '۱' => '1',
        '۲' => '2',
        '۳' => '3',
        '۴' => '4',
        '۵' => '5',
        '۶' => '6',
        '۷' => '7',
        '۸' => '8',
        '۹' => '9',
    ];

    public static function normalize(
        string $value
    ): string {
        $value = mb_strtolower(
            $value,
            'UTF-8'
        );

        $value = strtr(
            $value,
            self::CHARACTER_MAP
        );

        $value = preg_replace(
            '/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u',
            '',
            $value
        ) ?? $value;

        /*
         * ZWNJ / ZWJ / BOM act like word separators
         * for search purposes.
         */
        $value = preg_replace(
            '/[\x{200C}\x{200D}\x{FEFF}]+/u',
            ' ',
            $value
        ) ?? $value;

        /*
         * Treat punctuation and separators uniformly.
         */
        $value = preg_replace(
            '/[^\p{L}\p{N}]+/u',
            ' ',
            $value
        ) ?? $value;

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;

        return trim($value);
    }

    public static function compact(
        string $value
    ): string {
        $normalized =
            self::normalize($value);

        return preg_replace(
            '/\s+/u',
            '',
            $normalized
        ) ?? str_replace(
            ' ',
            '',
            $normalized
        );
    }

    /**
     * @return array<int, string>
     */
    public static function forms(
        string $value
    ): array {
        $normalized =
            self::normalize($value);

        $compact =
            preg_replace(
                '/\s+/u',
                '',
                $normalized
            ) ?? str_replace(
                ' ',
                '',
                $normalized
            );

        return array_values(
            array_unique(
                array_filter(
                    [
                        $normalized,
                        $compact,
                    ],
                    static fn (
                        string $form
                    ): bool =>
                        $form !== ''
                )
            )
        );
    }
}