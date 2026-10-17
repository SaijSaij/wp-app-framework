<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Query\Field;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Query\Condition;

/**
 * An int field of a meta class or a column of a table.
 *
 * @api Query fields
 */
#[NoService]
readonly class IntField extends Field
{
    /**
     * Matches values equal to $value.
     *
     * @param int $value The value to compare with.
     */
    public function equals(int $value): Condition
    {
        return $this->condition('=', [$value]);
    }

    /**
     * Matches values not equal to $value. On a nullable column, rows whose column is null match as well.
     *
     * @param int $value The value to compare with.
     */
    public function notEquals(int $value): Condition
    {
        return $this->condition('!=', [$value]);
    }

    /**
     * Matches values equal to one of $values. Throws an InvalidArgumentException if no value is given.
     *
     * @param int ...$values The values to compare with.
     */
    public function in(int ...$values): Condition
    {
        return $this->condition('IN', array_values($values));
    }

    /**
     * Matches values equal to none of $values. On a nullable column, rows whose column is null match as well. Throws an
     * InvalidArgumentException if no value is given.
     *
     * @param int ...$values The values to compare with.
     */
    public function notIn(int ...$values): Condition
    {
        return $this->condition('NOT IN', array_values($values));
    }

    /**
     * Matches values greater than $value.
     *
     * @param int $value The value to compare with.
     */
    public function greaterThan(int $value): Condition
    {
        return $this->condition('>', [$value]);
    }

    /**
     * Matches values greater than or equal to $value.
     *
     * @param int $value The value to compare with.
     */
    public function greaterOrEqual(int $value): Condition
    {
        return $this->condition('>=', [$value]);
    }

    /**
     * Matches values less than $value.
     *
     * @param int $value The value to compare with.
     */
    public function lessThan(int $value): Condition
    {
        return $this->condition('<', [$value]);
    }

    /**
     * Matches values less than or equal to $value.
     *
     * @param int $value The value to compare with.
     */
    public function lessOrEqual(int $value): Condition
    {
        return $this->condition('<=', [$value]);
    }

    /**
     * Matches values from $min to $max, both included.
     *
     * @param int $min The lowest value.
     * @param int $max The highest value.
     */
    public function between(int $min, int $max): Condition
    {
        return $this->condition('BETWEEN', [$min, $max]);
    }

    public function getCast(): string
    {
        return 'SIGNED';
    }
}
