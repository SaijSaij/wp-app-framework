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
 */
#[NoService]
readonly class DateTimeField extends Field
{
    public function equals(DateTimeInterface $value): Condition
    {
        return $this->condition('=', [self::utc($value)]);
    }

    public function notEquals(DateTimeInterface $value): Condition
    {
        return $this->condition('!=', [self::utc($value)]);
    }

    public function in(DateTimeInterface ...$values): Condition
    {
        return $this->condition('IN', array_map(self::utc(...), array_values($values)));
    }

    public function notIn(DateTimeInterface ...$values): Condition
    {
        return $this->condition('NOT IN', array_map(self::utc(...), array_values($values)));
    }

    /**
     * Times after $value.
     */
    public function greaterThan(DateTimeInterface $value): Condition
    {
        return $this->condition('>', [self::utc($value)]);
    }

    public function greaterOrEqual(DateTimeInterface $value): Condition
    {
        return $this->condition('>=', [self::utc($value)]);
    }

    /**
     * Times before $value.
     */
    public function lessThan(DateTimeInterface $value): Condition
    {
        return $this->condition('<', [self::utc($value)]);
    }

    public function lessOrEqual(DateTimeInterface $value): Condition
    {
        return $this->condition('<=', [self::utc($value)]);
    }

    /**
     * Times from $min to $max, both included.
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
