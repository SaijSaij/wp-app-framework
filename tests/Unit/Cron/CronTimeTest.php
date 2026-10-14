<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Cron;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Cron\CronInterval;
use Saij\WpAppFramework\Cron\CronTime;
use Saij\WpAppFramework\Cron\Weekday;

final class CronTimeTest extends TestCase
{
    private static function berlin(): DateTimeZone
    {
        return new DateTimeZone('Europe/Berlin');
    }

    private static function utc(string $time): int
    {
        return new DateTimeImmutable($time, new DateTimeZone('UTC'))->getTimestamp();
    }

    private static function local(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, self::berlin());
    }

    public function testOccurrenceOnAnOrdinaryDay(): void
    {
        self::assertSame(self::utc('2026-06-01 01:00'), CronTime::occurrence(self::local('2026-06-01'), '03:00'));
        self::assertSame(self::utc('2026-01-15 02:00'), CronTime::occurrence(self::local('2026-01-15 18:00'), '03:00'));
    }

    public function testATimeInTheGapOfTheChangeToSummerTimeMovesForward(): void
    {
        // 02:30 does not exist on 29 March 2026 in Berlin: 03:30 CEST.
        self::assertSame(self::utc('2026-03-29 01:30'), CronTime::occurrence(self::local('2026-03-29'), '02:30'));
        self::assertTrue(CronTime::isOccurrence(self::utc('2026-03-29 01:30'), CronInterval::Daily, '02:30', null, self::berlin()));
    }

    public function testTheFirstOfTheRepeatedHourCounts(): void
    {
        // 02:30 exists twice on 25 October 2026 in Berlin: 02:30 CEST (00:30 UTC) and 02:30 CET (01:30 UTC).
        self::assertSame(self::utc('2026-10-25 00:30'), CronTime::occurrence(self::local('2026-10-25 12:00'), '02:30'));
        self::assertTrue(CronTime::isOccurrence(self::utc('2026-10-25 00:30'), CronInterval::Daily, '02:30', null, self::berlin()));
        self::assertFalse(CronTime::isOccurrence(self::utc('2026-10-25 01:30'), CronInterval::Daily, '02:30', null, self::berlin()));
    }

    public function testATimeZoneWithAFixedOffset(): void
    {
        $offset = new DateTimeZone('+05:30');

        self::assertSame(self::utc('2026-03-28 21:30'), CronTime::occurrence(new DateTimeImmutable('2026-03-29', $offset), '03:00'));
        self::assertSame(self::utc('2026-03-29 21:30'), CronTime::next(self::utc('2026-03-29 12:00'), CronInterval::Daily, '03:00', null, $offset));
    }

    public function testTwiceDailyRunsAtTheTimeAndTwelveHoursLater(): void
    {
        $berlin = self::berlin();

        self::assertTrue(CronTime::isOccurrence(self::utc('2026-06-01 04:30'), CronInterval::TwiceDaily, '06:30', null, $berlin));
        self::assertTrue(CronTime::isOccurrence(self::utc('2026-06-01 16:30'), CronInterval::TwiceDaily, '06:30', null, $berlin));
        self::assertFalse(CronTime::isOccurrence(self::utc('2026-06-01 10:30'), CronInterval::TwiceDaily, '06:30', null, $berlin));
        self::assertSame(self::utc('2026-06-01 16:30'), CronTime::next(self::utc('2026-06-01 05:00'), CronInterval::TwiceDaily, '06:30', null, $berlin));
        // 18:00 and 06:00: the later time of the day comes first after midnight.
        self::assertSame(self::utc('2026-06-02 04:00'), CronTime::next(self::utc('2026-06-01 17:00'), CronInterval::TwiceDaily, '18:00', null, $berlin));
    }

    public function testWeeklyRunsOnTheWeekday(): void
    {
        $berlin = self::berlin();

        // Wednesday, 3 June 2026: the next Monday is 8 June.
        self::assertSame(self::utc('2026-06-08 06:00'), CronTime::next(self::utc('2026-06-03 12:00'), CronInterval::Weekly, '08:00', Weekday::Monday, $berlin));
        self::assertTrue(CronTime::isOccurrence(self::utc('2026-06-08 06:00'), CronInterval::Weekly, '08:00', Weekday::Monday, $berlin));
        self::assertFalse(CronTime::isOccurrence(self::utc('2026-06-09 06:00'), CronInterval::Weekly, '08:00', Weekday::Monday, $berlin));
        // On the Monday itself, after 08:00: a week later.
        self::assertSame(self::utc('2026-06-15 06:00'), CronTime::next(self::utc('2026-06-08 07:00'), CronInterval::Weekly, '08:00', Weekday::Monday, $berlin));
    }

    public function testNearestMovesAnEventBackToItsLocalTimeAfterAChangeOfDaylightSavingTime(): void
    {
        $berlin = self::berlin();

        // WP-Cron keeps the phase in UTC: 03:00 CET (02:00 UTC) became 04:00 CEST; back to 03:00 CEST, which is past.
        self::assertFalse(CronTime::isOccurrence(self::utc('2026-03-29 02:00'), CronInterval::Daily, '03:00', null, $berlin));
        self::assertSame(self::utc('2026-03-29 01:00'), CronTime::nearest(self::utc('2026-03-29 02:00'), CronInterval::Daily, '03:00', null, $berlin));
        // 03:00 CEST (01:00 UTC) became 02:00 CET; forward to 03:00 CET.
        self::assertFalse(CronTime::isOccurrence(self::utc('2026-10-25 01:00'), CronInterval::Daily, '03:00', null, $berlin));
        self::assertSame(self::utc('2026-10-25 02:00'), CronTime::nearest(self::utc('2026-10-25 01:00'), CronInterval::Daily, '03:00', null, $berlin));
    }

    public function testNearestAfterAChangeOfTheTimeZone(): void
    {
        $newYork = new DateTimeZone('America/New_York');

        // 03:00 in Berlin (01:00 UTC) is 21:00 in New York; 03:00 there (07:00 UTC) is 6 hours away.
        self::assertSame(self::utc('2026-06-01 07:00'), CronTime::nearest(self::utc('2026-06-01 01:00'), CronInterval::Daily, '03:00', null, $newYork));
        // Of two equally near occurrences the earlier.
        self::assertSame(self::utc('2026-06-01 03:00'), CronTime::nearest(self::utc('2026-06-01 15:00'), CronInterval::Daily, '03:00', null, new DateTimeZone('UTC')));
        // A weekly event moves to the nearest occurrence on its weekday.
        self::assertSame(self::utc('2026-06-08 06:00'), CronTime::nearest(self::utc('2026-06-07 06:00'), CronInterval::Weekly, '08:00', Weekday::Monday, self::berlin()));
    }

    /**
     * @return iterable<string, array{0: string, 1: CronInterval, 2: string, 3: Weekday|null}>
     */
    public static function rules(): iterable
    {
        foreach (['Europe/Berlin', 'America/Santiago', 'Australia/Lord_Howe', '+05:30'] as $zone) {
            foreach (['00:00', '02:30', '23:30'] as $at) {
                yield $zone . ' daily ' . $at => [$zone, CronInterval::Daily, $at, null];
                yield $zone . ' twice daily ' . $at => [$zone, CronInterval::TwiceDaily, $at, null];
                yield $zone . ' weekly ' . $at => [$zone, CronInterval::Weekly, $at, Weekday::Sunday];
            }
        }
    }

    /**
     * Every occurrence of a year is recognized as one and leads to itself, so the registrar never corrects an event
     * it has just scheduled.
     */
    #[DataProvider('rules')]
    public function testOccurrencesAreConsistentThroughTheYear(string $zone, CronInterval $interval, string $at, ?Weekday $weekday): void
    {
        $timezone = new DateTimeZone($zone);
        $previous = self::utc('2025-12-31 00:00');
        $count = 0;
        while ($previous < self::utc('2027-01-01 00:00')) {
            $next = CronTime::next($previous, $interval, $at, $weekday, $timezone);
            self::assertGreaterThan($previous, $next);
            self::assertTrue(CronTime::isOccurrence($next, $interval, $at, $weekday, $timezone), sprintf('%d is an occurrence.', $next));
            self::assertSame($next, CronTime::nearest($next, $interval, $at, $weekday, $timezone));
            if ($count > 0) {
                // Between two occurrences there is at least the shortest day (or half day) of a change of daylight saving time.
                self::assertGreaterThanOrEqual($interval === CronInterval::TwiceDaily ? 39600 : 82800, $next - $previous);
            }
            $previous = $next;
            $count++;
        }

        self::assertGreaterThan(50, $count);
    }
}
