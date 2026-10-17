<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Cron;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * The times of events with a fixed start time (`#[CronEvent(at: …)]`) in the
 * time zone of the site.
 *
 * WP-Cron keeps the phase of a recurring event in UTC, so after a change to or
 * from daylight saving time an event runs an hour off its local time. The
 * registrar checks the phase with isOccurrence() and moves a wrong one to the
 * nearest() occurrence.
 *
 * A time in the gap of a change to daylight saving time moves forward by the
 * gap (02:30 becomes 03:30), in the repeated hour of the change back the first
 * of the two times counts. The results depend only on the arguments.
 *
 * @internal
 */
#[NoService]
final class CronTime
{
    private const int DAY = 86400;

    /** Days searched before and after a timestamp: weekly events occur within 8 days, the others within 2. */
    private const int WEEKLY_SEARCH_DAYS = 8;

    private const int SEARCH_DAYS = 2;

    /**
     * Timestamp of the time of day $at on the local date of $localDate (its time zone).
     *
     * @param string $at `HH:MM`.
     */
    public static function occurrence(DateTimeImmutable $localDate, string $at): int
    {
        return self::occurrenceOn($localDate->format('Y-m-d'), $at, $localDate->getTimezone());
    }

    /**
     * Whether the timestamp is an occurrence of the event: `$at` (with CronInterval::TwiceDaily also 12 hours later)
     * on a date of the time zone, with CronInterval::Weekly on the weekday.
     *
     * @param string $at `HH:MM`.
     */
    public static function isOccurrence(int $timestamp, CronInterval $interval, string $at, ?Weekday $weekday, DateTimeZone $timezone): bool
    {
        $date = new DateTimeImmutable('@' . $timestamp)->setTimezone($timezone)->format('Y-m-d');
        if (in_array($timestamp, self::occurrences($date, $interval, $at, $weekday, $timezone), true)) {
            return true;
        }

        // A time in the gap of a change to daylight saving time just before midnight moves to the next date.
        return in_array($timestamp, self::occurrences(self::addDays($date, -1), $interval, $at, $weekday, $timezone), true);
    }

    /**
     * The first occurrence after the timestamp.
     *
     * @param string $at `HH:MM`.
     */
    public static function next(int $after, CronInterval $interval, string $at, ?Weekday $weekday, DateTimeZone $timezone): int
    {
        foreach (self::around($after, $interval, $at, $weekday, $timezone) as $occurrence) {
            if ($occurrence > $after) {
                return $occurrence;
            }
        }

        throw new LogicException(sprintf('No occurrence of %s at %s around %d.', $interval->name, $at, $after));
    }

    /**
     * The occurrence nearest to the timestamp; of two equally near ones the earlier.
     *
     * @param string $at `HH:MM`.
     */
    public static function nearest(int $timestamp, CronInterval $interval, string $at, ?Weekday $weekday, DateTimeZone $timezone): int
    {
        $nearest = null;
        foreach (self::around($timestamp, $interval, $at, $weekday, $timezone) as $occurrence) {
            if ($nearest === null || abs($occurrence - $timestamp) < abs($nearest - $timestamp)) {
                $nearest = $occurrence;
            }
        }

        return $nearest ?? throw new LogicException(sprintf('No occurrence of %s at %s around %d.', $interval->name, $at, $timestamp));
    }

    /**
     * The occurrences on the local dates from some days before to some days after the timestamp, in order.
     *
     * @return list<int>
     */
    private static function around(int $timestamp, CronInterval $interval, string $at, ?Weekday $weekday, DateTimeZone $timezone): array
    {
        $date = new DateTimeImmutable('@' . $timestamp)->setTimezone($timezone)->format('Y-m-d');
        $occurrences = [];
        $days = $interval === CronInterval::Weekly ? self::WEEKLY_SEARCH_DAYS : self::SEARCH_DAYS;
        for ($day = -$days; $day <= $days; $day++) {
            array_push($occurrences, ...self::occurrences(self::addDays($date, $day), $interval, $at, $weekday, $timezone));
        }

        return $occurrences;
    }

    /**
     * The occurrences on a local date, in order.
     *
     * @param string $date `Y-m-d`.
     * @return list<int>
     */
    private static function occurrences(string $date, CronInterval $interval, string $at, ?Weekday $weekday, DateTimeZone $timezone): array
    {
        return match ($interval) {
            CronInterval::Daily => [self::occurrenceOn($date, $at, $timezone)],
            CronInterval::TwiceDaily => self::twice($date, $at, $timezone),
            CronInterval::Weekly => self::weekday($date) === ($weekday ?? throw new LogicException('A weekly event with a start time needs a weekday.'))
                ? [self::occurrenceOn($date, $at, $timezone)]
                : [],
            CronInterval::Hourly => throw new LogicException('Hourly events have no start time.'),
        };
    }

    /**
     * $at and the time 12 hours later on a local date, in order.
     *
     * @return list<int>
     */
    private static function twice(string $date, string $at, DateTimeZone $timezone): array
    {
        [$hour, $minute] = self::time($at);
        $times = [self::occurrenceOn($date, $at, $timezone), self::occurrenceOn($date, sprintf('%02d:%02d', ($hour + 12) % 24, $minute), $timezone)];
        sort($times);

        return $times;
    }

    /**
     * @param string $date `Y-m-d`.
     */
    private static function occurrenceOn(string $date, string $at, DateTimeZone $timezone): int
    {
        [$year, $month, $day] = array_map(intval(...), explode('-', $date));
        [$hour, $minute] = self::time($at);
        // The wall time read as UTC; the offsets of the time zone before and after it lead to the real timestamp.
        $wall = (int) gmmktime($hour, $minute, 0, $month, $day, $year);
        $before = $timezone->getOffset(new DateTimeImmutable('@' . ($wall - self::DAY)));
        $after = $timezone->getOffset(new DateTimeImmutable('@' . ($wall + self::DAY)));
        if ($before === $after) {
            return $wall - $before;
        }

        // The offset changes around this date: the wall time exists once, twice (the repeated hour) or not at all (the gap).
        $valid = [];
        foreach ([$before, $after] as $offset) {
            if ($timezone->getOffset(new DateTimeImmutable('@' . ($wall - $offset))) === $offset) {
                $valid[] = $wall - $offset;
            }
        }

        // In the gap the time moves forward by the gap, as with PHP's own date arithmetic.
        return $valid === [] ? $wall - $before : min($valid);
    }

    /**
     * @return array{0: int, 1: int} Hour and minute.
     */
    private static function time(string $at): array
    {
        [$hour, $minute] = explode(':', $at) + [1 => '0'];

        return [(int) $hour, (int) $minute];
    }

    /**
     * @param string $date `Y-m-d`.
     */
    private static function weekday(string $date): Weekday
    {
        [$year, $month, $day] = array_map(intval(...), explode('-', $date));

        return Weekday::from((int) gmdate('N', (int) gmmktime(0, 0, 0, $month, $day, $year)));
    }

    /**
     * @param string $date `Y-m-d`.
     * @return string `Y-m-d`.
     */
    private static function addDays(string $date, int $days): string
    {
        [$year, $month, $day] = array_map(intval(...), explode('-', $date));

        return gmdate('Y-m-d', (int) gmmktime(0, 0, 0, $month, $day + $days, $year));
    }
}
