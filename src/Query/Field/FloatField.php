<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\Condition;

/**
 * A float field of a meta class.
 */
#[NoService]
final readonly class FloatField extends Field
{
    public function equals(float $value): Condition
    {
        return $this->condition('=', [$value]);
    }

    public function notEquals(float $value): Condition
    {
        return $this->condition('!=', [$value]);
    }

    public function in(float ...$values): Condition
    {
        return $this->condition('IN', array_values($values));
    }

    public function notIn(float ...$values): Condition
    {
        return $this->condition('NOT IN', array_values($values));
    }

    public function greaterThan(float $value): Condition
    {
        return $this->condition('>', [$value]);
    }

    public function greaterOrEqual(float $value): Condition
    {
        return $this->condition('>=', [$value]);
    }

    public function lessThan(float $value): Condition
    {
        return $this->condition('<', [$value]);
    }

    public function lessOrEqual(float $value): Condition
    {
        return $this->condition('<=', [$value]);
    }

    /**
     * Values from $min to $max, both included.
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
