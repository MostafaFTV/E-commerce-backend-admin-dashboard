<?php

namespace Tests\Unit;

use App\Support\JalaliCalendar;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class JalaliCalendarTest extends TestCase
{
    public function test_known_jalali_month_boundaries(): void
    {
        $this->assertSame(
            '2025-03-21',
            JalaliCalendar::monthStart(1404, 1)->format('Y-m-d')
        );

        $this->assertSame(
            '2026-03-21',
            JalaliCalendar::monthStart(1405, 1)->format('Y-m-d')
        );

        $this->assertSame(
            '2026-09-23',
            JalaliCalendar::monthStart(1405, 7)->format('Y-m-d')
        );

        $this->assertSame(
            '2026-10-23',
            JalaliCalendar::monthStart(1405, 8)->format('Y-m-d')
        );
    }

    public function test_tehran_midnight_converts_to_utc(): void
    {
        $this->assertSame(
            '2026-09-22 20:30:00',
            JalaliCalendar::monthStart(1405, 7)
                ->setTimezone('UTC')
                ->format('Y-m-d H:i:s')
        );
    }

    public function test_month_changes_at_tehran_midnight(): void
    {
        $before = CarbonImmutable::parse(
            '2026-09-22 20:29:59',
            'UTC'
        );

        $after = CarbonImmutable::parse(
            '2026-09-22 20:30:00',
            'UTC'
        );

        $this->assertSame(
            [1405, 6],
            JalaliCalendar::yearAndMonth($before)
        );

        $this->assertSame(
            [1405, 7],
            JalaliCalendar::yearAndMonth($after)
        );
    }

    public function test_leap_year_transition(): void
    {
        $this->assertSame(
            '2025-02-19',
            JalaliCalendar::monthStart(1403, 12)->format('Y-m-d')
        );

        $this->assertSame(
            30,
            (int) JalaliCalendar::monthStart(1403, 12)
                ->diffInDays(
                    JalaliCalendar::monthStart(1404, 1)
                )
        );
    }

    public function test_month_labels(): void
    {
        $this->assertSame(
            'مهر',
            JalaliCalendar::monthName(7)
        );

        $this->assertSame(
            'اسفند',
            JalaliCalendar::monthName(12)
        );
    }
}
