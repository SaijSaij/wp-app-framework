<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\Condition;

/**
 * A float field of a meta class or a column of a table.
 *
 * @api Query fields
 */
#[NoService]
readonly class FloatField extends Field
{
    /**
     * Matches values equal to $value.
     *
     * @param float $value The value to compare with; on a table column, a finite number.
     */
    public function equals(float $value): Condition
    {
        return $this->condition('=', [$value]);
    }

    /**
     * Matches values not equal to $value. On a nullable column, rows whose column is null match as well.
     *
     * @param float $value The value to compare with; on a table column, a finite number.
     */
    public function notEquals(float $value): Condition
    {
        return $this->condition('!=', [$value]);
    }

    /**
     * Matches values equal to one of $values. Throws an InvalidArgumentException if no value is given.
     *
     * @param float ...$values The values to compare with; on a table column, finite numbers.
     */
    public function in(float ...$values): Condition
    {
        return $this->condition('IN', array_values($values));
    }

    /**
     * Matches values equal to none of $values. On a nullable column, rows whose column is null match as well. Throws an
     * InvalidArgumentException if no value is given.
     *
     * @param float ...$values The values to compare with; on a table column, finite numbers.
     */
    public function notIn(float ...$values): Condition
    {
        return $this->condition('NOT IN', array_values($values));
    }

    /**
     * Matches values greater than $value.
     *
     * @param float $value The value to compare with; on a table column, a finite number.
     */
    public function greaterThan(float $value): Condition
    {
        return $this->condition('>', [$value]);
    }

    /**
     * Matches values greater than or equal to $value.
     *
     * @param float $value The value to compare with; on a table column, a finite number.
     */
    public function greaterOrEqual(float $value): Condition
    {
        return $this->condition('>=', [$value]);
    }

    /**
     * Matches values less than $value.
     *
     * @param float $value The value to compare with; on a table column, a finite number.
     */
    public function lessThan(float $value): Condition
    {
        return $this->condition('<', [$value]);
    }

    /**
     * Matches values less than or equal to $value.
     *
     * @param float $value The value to compare with; on a table column, a finite number.
     */
    public function lessOrEqual(float $value): Condition
    {
        return $this->condition('<=', [$value]);
    }

    /**
     * Matches values from $min to $max, both included.
     *
     * @param float $min The lowest value; on a table column, a finite number.
     * @param float $max The highest value; on a table column, a finite number.
     */
    public function between(float $min, float $max): Condition
    {
        return $this->condition('BETWEEN', [$min, $max]);
    }

    public function getCast(): string
    {
        return 'DECIMAL(65,30)';
    }
}
