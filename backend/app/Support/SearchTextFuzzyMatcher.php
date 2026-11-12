<?php

namespace App\Support;

final class SearchTextFuzzyMatcher
{
    public static function matches(
        string $query,
        array $candidates
    ): bool {
        $query =
            SearchTextNormalizer::compact(
                $query
            );

        $queryLength =
            self::length($query);

        /*
         * Very short fuzzy queries create too many
         * false-positive product matches.
         */
        if ($queryLength < 4) {
            return false;
        }

        $maxDistance =
            self::maxDistance(
                $queryLength
            );

        foreach ($candidates as $candidate) {
            if (
                !is_string($candidate) ||
                trim($candidate) === ''
            ) {
                continue;
            }

            foreach (
                self::candidateForms(
                    $candidate
                ) as $candidateForm
            ) {
                $candidateLength =
                    self::length(
                        $candidateForm
                    );

                if (
                    abs(
                        $queryLength -
                        $candidateLength
                    ) > $maxDistance
                ) {
                    continue;
                }

                if (
                    self::distanceWithin(
                        $query,
                        $candidateForm,
                        $maxDistance
                    )
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private static function candidateForms(
        string $value
    ): array {
        $normalized =
            SearchTextNormalizer::normalize(
                $value
            );

        if ($normalized === '') {
            return [];
        }

        $forms = [
            SearchTextNormalizer::compact(
                $normalized
            ),
        ];

        foreach (
            preg_split(
                '/\s+/u',
                $normalized,
                -1,
                PREG_SPLIT_NO_EMPTY
            ) ?: [] as $token
        ) {
            $forms[] =
                SearchTextNormalizer::compact(
                    $token
                );
        }

        return array_values(
            array_unique(
                array_filter(
                    $forms,
                    static fn (
                        string $form
                    ): bool =>
                        $form !== ''
                )
            )
        );
    }

    private static function maxDistance(
        int $length
    ): int {
        return match (true) {
            $length <= 7 => 1,
            $length <= 12 => 2,
            default => 3,
        };
    }

    private static function distanceWithin(
        string $left,
        string $right,
        int $maxDistance
    ): bool {
        $leftChars =
            self::characters($left);

        $rightChars =
            self::characters($right);

        $leftLength =
            count($leftChars);

        $rightLength =
            count($rightChars);

        if (
            abs(
                $leftLength -
                $rightLength
            ) > $maxDistance
        ) {
            return false;
        }

        $previousPrevious = null;

        $previous =
            range(
                0,
                $rightLength
            );

        for (
            $i = 1;
            $i <= $leftLength;
            $i++
        ) {
            $current = [$i];

            $rowMinimum = $i;

            for (
                $j = 1;
                $j <= $rightLength;
                $j++
            ) {
                $cost =
                    $leftChars[$i - 1] ===
                    $rightChars[$j - 1]
                        ? 0
                        : 1;

                $value = min(
                    $previous[$j] + 1,
                    $current[$j - 1] + 1,
                    $previous[$j - 1] + $cost
                );

                /*
                 * Adjacent transposition support.
                 */
                if (
                    $i > 1 &&
                    $j > 1 &&
                    $previousPrevious !== null &&
                    $leftChars[$i - 1] ===
                        $rightChars[$j - 2] &&
                    $leftChars[$i - 2] ===
                        $rightChars[$j - 1]
                ) {
                    $value = min(
                        $value,
                        $previousPrevious[$j - 2] + 1
                    );
                }

                $current[$j] = $value;

                $rowMinimum = min(
                    $rowMinimum,
                    $value
                );
            }

            if ($rowMinimum > $maxDistance) {
                return false;
            }

            $previousPrevious =
                $previous;

            $previous =
                $current;
        }

        return $previous[$rightLength]
            <= $maxDistance;
    }

    /**
     * @return array<int, string>
     */
    private static function characters(
        string $value
    ): array {
        return preg_split(
            '//u',
            $value,
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];
    }

    private static function length(
        string $value
    ): int {
        return count(
            self::characters(
                $value
            )
        );
    }
}