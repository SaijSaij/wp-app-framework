<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use BackedEnum;
use InvalidArgumentException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\Condition;

/**
 * A backed enum field of a meta class, stored as the value of its case.
 *
 * @template E of BackedEnum
 */
#[NoService]
final readonly class EnumField extends Field
{
    /**
     * @param class-string    $metaClass The meta class (#[PostMeta]).
     * @param string          $field     The name of the constructor parameter.
     * @param class-string<E> $enum      The enum of the field.
     */
    public function __construct(string $metaClass, string $field, private string $enum)
    {
        parent::__construct($metaClass, $field);
    }

    /**
     * @param E $case
     */
    public function equals(BackedEnum $case): Condition
    {
        return $this->condition('=', [$this->value($case)]);
    }

    /**
     * @param E $case
     */
    public function notEquals(BackedEnum $case): Condition
    {
        return $this->condition('!=', [$this->value($case)]);
    }

    /**
     * @param E ...$cases
     */
    public function in(BackedEnum ...$cases): Condition
    {
        return $this->condition('IN', array_map($this->value(...), array_values($cases)));
    }

    /**
     * @param E ...$cases
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
            throw new InvalidArgumentException(sprintf('%s::$%s is a %s, %s given.', $this->getMetaClass(), $this->getField(), $this->enum, $case::class));
        }

        return $case->value;
    }
}
