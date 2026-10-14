<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\Condition;

/**
 * An int field of a meta class or a column of a table.
 */
#[NoService]
readonly class IntField extends Field
{
    public function equals(int $value): Condition
    {
        return $this->condition('=', [$value]);
    }

    public function notEquals(int $value): Condition
    {
        return $this->condition('!=', [$value]);
    }

    public function in(int ...$values): Condition
    {
        return $this->condition('IN', array_values($values));
    }

    public function notIn(int ...$values): Condition
    {
        return $this->condition('NOT IN', array_values($values));
    }

    public function greaterThan(int $value): Condition
    {
        return $this->condition('>', [$value]);
    }

    public function greaterOrEqual(int $value): Condition
    {
        return $this->condition('>=', [$value]);
    }

    public function lessThan(int $value): Condition
    {
        return $this->condition('<', [$value]);
    }

    public function lessOrEqual(int $value): Condition
    {
        return $this->condition('<=', [$value]);
    }

    /**
     * Values from $min to $max, both included.
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
