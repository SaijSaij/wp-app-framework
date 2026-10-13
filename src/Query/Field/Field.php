<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use InvalidArgumentException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\Condition;
use Saij\WpAppFramework\Query\MetaCondition;

/**
 * A field of a meta class in queries: its methods create conditions, and the query sorts by it. Created by the
 * static methods of a class with #[MetaFields]; the subclass matches the type of the field.
 *
 * A meta value that is not stored counts as the default of its constructor parameter, in conditions and in the
 * sort order.
 */
#[NoService]
abstract readonly class Field
{
    /**
     * @param class-string $metaClass The meta class (#[PostMeta]).
     * @param string       $field     The name of the constructor parameter.
     */
    public function __construct(
        private string $metaClass,
        private string $field,
    ) {}

    /**
     * @return class-string
     */
    public function getMetaClass(): string
    {
        return $this->metaClass;
    }

    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @internal The enum or post class of an EnumField or PostField.
     *
     * @return class-string|null
     */
    public function getTypeClass(): ?string
    {
        return null;
    }

    /**
     * @internal The SQL type the stored value is cast to.
     */
    abstract public function getCast(): string;

    /**
     * @internal A stored or compared value as the database compares it after the cast.
     */
    public function normalize(mixed $value): int|float|string
    {
        $cast = $this->getCast();
        if ($cast === 'SIGNED') {
            return is_numeric($value) || is_bool($value) ? (int) $value : 0;
        }
        if (str_starts_with($cast, 'DECIMAL')) {
            return is_numeric($value) ? (float) $value : 0.0;
        }

        return mb_strtolower(is_scalar($value) ? (string) $value : '');
    }

    /**
     * @param '='|'!='|'>'|'>='|'<'|'<='|'IN'|'NOT IN'|'BETWEEN'|'LIKE' $operator
     * @param list<int|float|string>                                     $values
     */
    protected function condition(string $operator, array $values): Condition
    {
        if ($values === []) {
            throw new InvalidArgumentException(sprintf('%s::$%s: %s needs at least one value.', $this->metaClass, $this->field, $operator));
        }

        return new MetaCondition($this, $operator, $values);
    }
}
