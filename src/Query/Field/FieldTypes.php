<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Value\ValueFactory;

/**
 * Which field class belongs to a meta field: shared by the build, the query at run time and the generator.
 * Queryable are fields on the top level of a meta class whose value is stored as one scalar; lists and value
 * classes are stored serialized.
 *
 * @internal
 *
 * @phpstan-import-type Value from ValueFactory
 */
#[NoService]
final class FieldTypes
{
    /**
     * The field class and, for enums and post references, the class of the type; null if the value is not
     * queryable.
     *
     * @param Value $value
     * @return array{class-string<Field>, class-string|null}|null
     */
    public static function expected(array $value): ?array
    {
        return match ($value['type']) {
            'string' => [StringField::class, null],
            'float' => [FloatField::class, null],
            'bool' => [BoolField::class, null],
            'enum' => [EnumField::class, $value['class']],
            'int' => match (true) {
                isset($value['reference']['class']) => [PostField::class, $value['reference']['class']],
                // References by ID and images (top-level nullable ints are IDs).
                isset($value['reference']) || $value['nullable'] => [IdField::class, null],
                default => [IntField::class, null],
            },
            'object', 'list' => null,
        };
    }

    /**
     * Why a field object does not fit the meta field, or null if it does.
     *
     * @param Value $value
     */
    public static function mismatch(Field $field, array $value): ?string
    {
        $expected = self::expected($value);
        if ($expected === null) {
            return sprintf('%s::$%s is a list or value class, stored serialized; only fields with one int, float, string, bool, enum, ID or post can be queried', $field->getMetaClass(), $field->getField());
        }
        [$class, $typeClass] = $expected;
        if (!$field instanceof $class) {
            return sprintf('%s::$%s needs %s, %s given', $field->getMetaClass(), $field->getField(), self::describe($class, $typeClass), $field::class);
        }
        if ($field->getTypeClass() !== $typeClass) {
            return sprintf('%s::$%s needs %s, %s given', $field->getMetaClass(), $field->getField(), self::describe($class, $typeClass), self::describe($field::class, $field->getTypeClass()));
        }

        return null;
    }

    /**
     * @param class-string<Field> $class
     */
    public static function describe(string $class, ?string $typeClass): string
    {
        return $typeClass === null ? $class : sprintf('%s<%s>', $class, $typeClass);
    }
}
