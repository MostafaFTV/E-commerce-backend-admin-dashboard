<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use LogicException;

final class JalaliCalendar
{
    private const BREAKS = [
        -61, 9, 38, 199, 426, 686, 756, 818,
        1111, 1181, 1210, 1635, 2060, 2097,
        2192, 2262, 2324, 2394, 2456, 3178,
    ];

    private const MONTH_NAMES = [
        'فروردین', 'اردیبهشت', 'خرداد',
        'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر',
        'دی', 'بهمن', 'اسفند',
    ];

    public static function monthName(int $month): string
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Invalid Jalali month.');
        }

        return self::MONTH_NAMES[$month - 1];
    }

    public static function monthStart(
        int $year,
        int $month
    ): CarbonImmutable {
        if ($year < 1300 || $year > 1499) {
            throw new InvalidArgumentException(
                'Jalali year outside supported reporting range.'
            );
        }

        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Invalid Jalali month.');
        }

        $daysBefore = $month <= 7
            ? ($month - 1) * 31
            : 186 + ($month - 7) * 30;

        return CarbonImmutable::createSafe(
            $year + 621,
            3,
            self::marchDay($year),
            0,
            0,
            0,
            'Asia/Tehran'
        )->addDays($daysBefore);
    }

    public static function yearAndMonth(
        CarbonImmutable $date
    ): array {
        $local = $date->setTimezone('Asia/Tehran');
        $year = $local->year - 621;

        if ($local->lt(self::monthStart($year, 1))) {
            $year--;
        }

        for ($month = 12; $month >= 1; $month--) {
            if ($local->gte(self::monthStart($year, $month))) {
                return [$year, $month];
            }
        }

        throw new LogicException('Unable to resolve Jalali month.');
    }

    private static function marchDay(int $year): int
    {
        $gregorianYear = $year + 621;
        $leapJ = -14;
        $previous = self::BREAKS[0];
        $jump = 0;

        for ($i = 1; $i < count(self::BREAKS); $i++) {
            $next = self::BREAKS[$i];
            $jump = $next - $previous;

            if ($year < $next) {
                break;
            }

            $leapJ += intdiv($jump, 33) * 8
                + intdiv($jump % 33, 4);

            $previous = $next;
        }

        $n = $year - $previous;

        $leapJ += intdiv($n, 33) * 8
            + intdiv(($n % 33) + 3, 4);

        if ($jump % 33 === 4 && $jump - $n === 4) {
            $leapJ++;
        }

        $leapG = intdiv($gregorianYear, 4)
            - intdiv(
                (intdiv($gregorianYear, 100) + 1) * 3,
                4
            )
            - 150;

        return 20 + $leapJ - $leapG;
    }
}
