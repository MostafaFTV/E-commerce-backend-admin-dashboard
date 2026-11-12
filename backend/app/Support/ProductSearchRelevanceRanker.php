<?php

namespace App\Support;

use App\Models\Product;

final class ProductSearchRelevanceRanker
{
    private const NO_MATCH = 1000;

    public static function score(
        Product $product,
        string $query
    ): int {
        $normalizedQuery =
            SearchTextNormalizer::normalize(
                $query
            );

        $compactQuery =
            SearchTextNormalizer::compact(
                $query
            );

        if (
            $normalizedQuery === '' &&
            $compactQuery === ''
        ) {
            return self::NO_MATCH;
        }

        $groups =
            ProductSearchIndexer::candidateGroups(
                $product
            );

        $bestScore = min(
            self::bestCandidateScore(
                $groups['names'],
                $normalizedQuery,
                $compactQuery,
                [
                    'exact' => 0,
                    'compact_exact' => 1,
                    'prefix' => 10,
                    'compact_prefix' => 11,
                    'token' => 25,
                    'contains' => 30,
                    'compact_contains' => 31,
                ]
            ),

            self::bestCandidateScore(
                $groups['brand'],
                $normalizedQuery,
                $compactQuery,
                [
                    'exact' => 20,
                    'compact_exact' => 21,
                    'prefix' => 22,
                    'compact_prefix' => 23,
                    'token' => 35,
                    'contains' => 40,
                    'compact_contains' => 41,
                ]
            ),

            self::bestCandidateScore(
                $groups['slug'],
                $normalizedQuery,
                $compactQuery,
                [
                    'exact' => 24,
                    'compact_exact' => 26,
                    'prefix' => 35,
                    'compact_prefix' => 36,
                    'token' => 42,
                    'contains' => 45,
                    'compact_contains' => 46,
                ]
            )
        );

        if ($bestScore !== self::NO_MATCH) {
            return $bestScore;
        }

        if (
            SearchTextFuzzyMatcher::matches(
                $query,
                $groups['names']
            )
        ) {
            return 100;
        }

        if (
            SearchTextFuzzyMatcher::matches(
                $query,
                $groups['brand']
            )
        ) {
            return 110;
        }

        if (
            SearchTextFuzzyMatcher::matches(
                $query,
                $groups['slug']
            )
        ) {
            return 120;
        }

        return self::NO_MATCH;
    }

    /**
     * @param array<int, string> $candidates
     * @param array<string, int> $scores
     */
    private static function bestCandidateScore(
        array $candidates,
        string $normalizedQuery,
        string $compactQuery,
        array $scores
    ): int {
        $bestScore =
            self::NO_MATCH;

        foreach ($candidates as $candidate) {
            $bestScore = min(
                $bestScore,
                self::candidateScore(
                    $normalizedQuery,
                    $compactQuery,
                    $candidate,
                    $scores
                )
            );
        }

        return $bestScore;
    }

    /**
     * @param array<string, int> $scores
     */
    private static function candidateScore(
        string $normalizedQuery,
        string $compactQuery,
        string $candidate,
        array $scores
    ): int {
        $normalizedCandidate =
            SearchTextNormalizer::normalize(
                $candidate
            );

        $compactCandidate =
            SearchTextNormalizer::compact(
                $candidate
            );

        if (
            $normalizedQuery !== '' &&
            $normalizedCandidate ===
                $normalizedQuery
        ) {
            return $scores['exact'];
        }

        if (
            $compactQuery !== '' &&
            $compactCandidate ===
                $compactQuery
        ) {
            return $scores[
                'compact_exact'
            ];
        }

        if (
            $normalizedQuery !== '' &&
            str_starts_with(
                $normalizedCandidate,
                $normalizedQuery
            )
        ) {
            return $scores['prefix'];
        }

        if (
            $compactQuery !== '' &&
            str_starts_with(
                $compactCandidate,
                $compactQuery
            )
        ) {
            return $scores[
                'compact_prefix'
            ];
        }

        if (
            $normalizedQuery !== '' &&
            in_array(
                $normalizedQuery,
                preg_split(
                    '/\s+/u',
                    $normalizedCandidate,
                    -1,
                    PREG_SPLIT_NO_EMPTY
                ) ?: [],
                true
            )
        ) {
            return $scores['token'];
        }

        if (
            $normalizedQuery !== '' &&
            str_contains(
                $normalizedCandidate,
                $normalizedQuery
            )
        ) {
            return $scores['contains'];
        }

        if (
            $compactQuery !== '' &&
            str_contains(
                $compactCandidate,
                $compactQuery
            )
        ) {
            return $scores[
                'compact_contains'
            ];
        }

        return self::NO_MATCH;
    }
}