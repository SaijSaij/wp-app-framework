<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use BackedEnum;
use InvalidArgumentException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\Condition;

/**
 * A backed enum field of a meta class or a column of a table, stored as the value of its case.
 *
 * @template E of BackedEnum
 *
 * @api Query fields
 */
#[NoService]
readonly class EnumField extends Field
{
    /**
     * @param class-string    $class     The class the field belongs to: a meta class (#[PostMeta]) or row class (#[Table]).
     * @param string          $field     The name of the constructor parameter.
     * @param class-string<E> $enum      The enum of the field.
     */
    public function __construct(string $class, string $field, private string $enum)
    {
        parent::__construct($class, $field);
    }

    /**
     * Matches the case $case. Throws an InvalidArgumentException if $case is a case of another enum.
     *
     * @param E $case A case of the enum of the field.
     */
    public function equals(BackedEnum $case): Condition
    {
        return $this->condition('=', [$this->value($case)]);
    }

    /**
     * Matches every case but $case. On a nullable column, rows whose column is null match as well. Throws an
     * InvalidArgumentException if $case is a case of another enum.
     *
     * @param E $case A case of the enum of the field.
     */
    public function notEquals(BackedEnum $case): Condition
    {
        return $this->condition('!=', [$this->value($case)]);
    }

    /**
     * Matches one of the cases $cases. Throws an InvalidArgumentException if no case or a case of another enum is
     * given.
     *
     * @param E ...$cases Cases of the enum of the field.
     */
    public function in(BackedEnum ...$cases): Condition
    {
        return $this->condition('IN', array_map($this->value(...), array_values($cases)));
    }

    /**
     * Matches none of the cases $cases. On a nullable column, rows whose column is null match as well. Throws an
     * InvalidArgumentException if no case or a case of another enum is given.
     *
     * @param E ...$cases Cases of the enum of the field.
     */
    public function notIn(BackedEnum ...$cases): Condition
    {
        return $this->condition('NOT IN', array_map($this->value(...), array_values($cases)));
    }

    /**
     * @return class-string<E>
     */
    public function getTypeClass(): string
    {
        return $this->enum;
    }

    public function getCast(): string
    {
        $case = $this->enum::cases()[0] ?? null;

        return $case !== null && is_int($case->value) ? 'SIGNED' : 'CHAR';
    }

    private function value(BackedEnum $case): int|string
    {
        if (!$case instanceof $this->enum) {
            throw new InvalidArgumentException(sprintf('%s::$%s is a %s, %s given.', $this->getClass(), $this->getField(), $this->enum, $case::class));
        }

        return $case->value;
    }
}
