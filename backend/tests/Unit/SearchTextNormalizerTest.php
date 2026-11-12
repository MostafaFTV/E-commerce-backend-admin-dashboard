<?php

namespace Tests\Unit;

use App\Support\SearchTextNormalizer;
use PHPUnit\Framework\TestCase;

class SearchTextNormalizerTest extends TestCase
{
    public function test_it_normalizes_persian_spacing_forms(): void
    {
        $this->assertSame(
            'اسپرسوساز',
            SearchTextNormalizer::compact(
                'اسپرسوساز'
            )
        );

        $this->assertSame(
            'اسپرسوساز',
            SearchTextNormalizer::compact(
                'اسپرسو ساز'
            )
        );

        $this->assertSame(
            'اسپرسوساز',
            SearchTextNormalizer::compact(
                "اسپرسو\u{200C}ساز"
            )
        );
    }

    public function test_it_normalizes_arabic_persian_character_variants(): void
    {
        $this->assertSame(
            'کالای یک',
            SearchTextNormalizer::normalize(
                'كالاي يک'
            )
        );
    }

    public function test_it_removes_diacritics_and_normalizes_digits(): void
    {
        $this->assertSame(
            'قهوه 123',
            SearchTextNormalizer::normalize(
                'قَهوه ۱۲٣'
            )
        );
    }

    public function test_it_returns_normalized_and_compact_forms(): void
    {
        $this->assertSame(
            [
                'اسپرسو ساز',
                'اسپرسوساز',
            ],
            SearchTextNormalizer::forms(
                "اسپرسو\u{200C}ساز"
            )
        );
    }
}