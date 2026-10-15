<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\Condition;

/**
 * A time column of a table (`DateTimeImmutable`), stored in UTC to the second; times of other time zones are
 * converted before they are compared. Meta fields have no time type.
 *
 * @api Query fields
 */
#[NoService]
readonly class DateTimeField extends Field
{
    /**
     * Matches times equal to $value.
     *
     * @param DateTimeInterface $value The time to compare with, in any time zone; compared to the second.
     */
    public function equals(DateTimeInterface $value): Condition
    {
        return $this->condition('=', [self::utc($value)]);
    }

    /**
     * Matches times not equal to $value. On a nullable column, rows whose column is null match as well.
     *
     * @param DateTimeInterface $value The time to compare with, in any time zone; compared to the second.
     */
    public function notEquals(DateTimeInterface $value): Condition
    {
        return $this->condition('!=', [self::utc($value)]);
    }

    /**
     * Matches times equal to one of $values. Throws an InvalidArgumentException if no value is given.
     *
     * @param DateTimeInterface ...$values The times to compare with, in any time zone; compared to the second.
     */
    public function in(DateTimeInterface ...$values): Condition
    {
        return $this->condition('IN', array_map(self::utc(...), array_values($values)));
    }

    /**
     * Matches times equal to none of $values. On a nullable column, rows whose column is null match as well. Throws an
     * InvalidArgumentException if no value is given.
     *
     * @param DateTimeInterface ...$values The times to compare with, in any time zone; compared to the second.
     */
    public function notIn(DateTimeInterface ...$values): Condition
    {
        return $this->condition('NOT IN', array_map(self::utc(...), array_values($values)));
    }

    /**
     * Matches times after $value.
     *
     * @param DateTimeInterface $value The time to compare with, in any time zone; compared to the second.
     */
    public function greaterThan(DateTimeInterface $value): Condition
    {
        return $this->condition('>', [self::utc($value)]);
    }

    /**
     * Matches times at or after $value.
     *
     * @param DateTimeInterface $value The time to compare with, in any time zone; compared to the second.
     */
    public function greaterOrEqual(DateTimeInterface $value): Condition
    {
        return $this->condition('>=', [self::utc($value)]);
    }

    /**
     * Matches times before $value.
     *
     * @param DateTimeInterface $value The time to compare with, in any time zone; compared to the second.
     */
    public function lessThan(DateTimeInterface $value): Condition
    {
        return $this->condition('<', [self::utc($value)]);
    }

    /**
     * Matches times at or before $value.
     *
     * @param DateTimeInterface $value The time to compare with, in any time zone; compared to the second.
     */
    public function lessOrEqual(DateTimeInterface $value): Condition
    {
        return $this->condition('<=', [self::utc($value)]);
    }

    /**
     * Matches times from $min to $max, both included.
     *
     * @param DateTimeInterface $min The earliest time, in any time zone.
     * @param DateTimeInterface $max The latest time, in any time zone.
     */
    public function between(DateTimeInterface $min, DateTimeInterface $max): Condition
    {
        return $this->condition('BETWEEN', [self::utc($min), self::utc($max)]);
    }

    public function getCast(): string
    {
        return 'DATETIME';
    }

    /**
     * @internal The format of times in the database: UTC, `Y-m-d H:i:s`.
     */
    public static function utc(DateTimeInterface $time): string
    {
        return DateTimeImmutable::createFromInterface($time)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
