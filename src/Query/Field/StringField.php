<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Query\Field;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Query\Condition;

/**
 * A string field of a meta class (also #[PlainText], #[RichText] and #[Url]) or a string column of a table; compared
 * without regard to case, like the default collations of the database.
 *
 * @api Query fields
 */
#[NoService]
readonly class StringField extends Field
{
    /**
     * Matches values equal to $value.
     *
     * @param string $value The value to compare with, without regard to case.
     */
    public function equals(string $value): Condition
    {
        return $this->condition('=', [$value]);
    }

    /**
     * Matches values not equal to $value. On a nullable column, rows whose column is null match as well.
     *
     * @param string $value The value to compare with, without regard to case.
     */
    public function notEquals(string $value): Condition
    {
        return $this->condition('!=', [$value]);
    }

    /**
     * Matches values equal to one of $values. Throws an InvalidArgumentException if no value is given.
     *
     * @param string ...$values The values to compare with, without regard to case.
     */
    public function in(string ...$values): Condition
    {
        return $this->condition('IN', array_values($values));
    }

    /**
     * Matches values equal to none of $values. On a nullable column, rows whose column is null match as well. Throws an
     * InvalidArgumentException if no value is given.
     *
     * @param string ...$values The values to compare with, without regard to case.
     */
    public function notIn(string ...$values): Condition
    {
        return $this->condition('NOT IN', array_values($values));
    }

    /**
     * Matches values that sort after $value in the order of the database, without regard to case.
     *
     * @param string $value The value to compare with, without regard to case.
     */
    public function greaterThan(string $value): Condition
    {
        return $this->condition('>', [$value]);
    }

    /**
     * Matches values that sort after $value or are equal to it, without regard to case.
     *
     * @param string $value The value to compare with, without regard to case.
     */
    public function greaterOrEqual(string $value): Condition
    {
        return $this->condition('>=', [$value]);
    }

    /**
     * Matches values that sort before $value in the order of the database, without regard to case.
     *
     * @param string $value The value to compare with, without regard to case.
     */
    public function lessThan(string $value): Condition
    {
        return $this->condition('<', [$value]);
    }

    /**
     * Matches values that sort before $value or are equal to it, without regard to case.
     *
     * @param string $value The value to compare with, without regard to case.
     */
    public function lessOrEqual(string $value): Condition
    {
        return $this->condition('<=', [$value]);
    }

    /**
     * Matches values from $min to $max, both included. Compared without regard to case.
     *
     * @param string $min The lowest value in the order of the database.
     * @param string $max The highest value in the order of the database.
     */
    public function between(string $min, string $max): Condition
    {
        return $this->condition('BETWEEN', [$min, $max]);
    }

    /**
     * Matches values that contain $text, without regard to case.
     *
     * @param string $text The text to search for; `%` and `_` are matched literally.
     */
    public function contains(string $text): Condition
    {
        return $this->condition('LIKE', [$text]);
    }

    public function getCast(): string
    {
        return 'CHAR';
    }
}
