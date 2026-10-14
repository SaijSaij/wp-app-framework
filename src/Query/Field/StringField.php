<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\Condition;

/**
 * A string field of a meta class (also #[PlainText], #[RichText] and #[Url]) or a string column of a table; compared
 * without regard to case, like the default collations of the database.
 */
#[NoService]
readonly class StringField extends Field
{
    public function equals(string $value): Condition
    {
        return $this->condition('=', [$value]);
    }

    public function notEquals(string $value): Condition
    {
        return $this->condition('!=', [$value]);
    }

    public function in(string ...$values): Condition
    {
        return $this->condition('IN', array_values($values));
    }

    public function notIn(string ...$values): Condition
    {
        return $this->condition('NOT IN', array_values($values));
    }

    public function greaterThan(string $value): Condition
    {
        return $this->condition('>', [$value]);
    }

    public function greaterOrEqual(string $value): Condition
    {
        return $this->condition('>=', [$value]);
    }

    public function lessThan(string $value): Condition
    {
        return $this->condition('<', [$value]);
    }

    public function lessOrEqual(string $value): Condition
    {
        return $this->condition('<=', [$value]);
    }

    /**
     * Values from $min to $max, both included.
     */
    public function between(string $min, string $max): Condition
    {
        return $this->condition('BETWEEN', [$min, $max]);
    }

    /**
     * Values that contain $text (SQL LIKE; % and _ are matched literally).
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
