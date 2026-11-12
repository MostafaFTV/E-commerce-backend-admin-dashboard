<?php

namespace Tests\Unit;

use App\Support\SearchTextFuzzyMatcher;
use PHPUnit\Framework\TestCase;

class SearchTextFuzzyMatcherTest extends TestCase
{
    public function test_it_matches_a_single_persian_typo(): void
    {
        $this->assertTrue(
            SearchTextFuzzyMatcher::matches(
                'چای سار',
                [
                    'چای ساز',
                ]
            )
        );
    }

    public function test_it_matches_a_persian_transposition(): void
    {
        $this->assertTrue(
            SearchTextFuzzyMatcher::matches(
                'کفگری',
                [
                    'کفگیر',
                ]
            )
        );
    }

    public function test_it_matches_an_english_typo(): void
    {
        $this->assertTrue(
            SearchTextFuzzyMatcher::matches(
                'electirc kettle',
                [
                    'Electric Kettle',
                ]
            )
        );
    }

    public function test_it_can_match_individual_tokens(): void
    {
        $this->assertTrue(
            SearchTextFuzzyMatcher::matches(
                'دلونگیی',
                [
                    'DeLonghi دلونگی Coffee Maker',
                ]
            )
        );
    }

    public function test_it_rejects_unrelated_persian_text(): void
    {
        $this->assertFalse(
            SearchTextFuzzyMatcher::matches(
                'چاقوی آشپزخانه',
                [
                    'کتری برقی',
                    'همزن برقی',
                    'چای ساز',
                ]
            )
        );
    }

    public function test_it_disables_fuzzy_matching_for_very_short_queries(): void
    {
        $this->assertFalse(
            SearchTextFuzzyMatcher::matches(
                'کت',
                [
                    'کف',
                ]
            )
        );
    }
}