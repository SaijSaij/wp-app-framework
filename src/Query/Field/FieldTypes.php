<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query\Field;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Table\TableCollector;
use Saij\WpAppFramework\Value\ValueFactory;

/**
 * Which field class belongs to a meta field or a column of a table: shared by the build, the queries at run time and
 * the generators. Queryable are fields on the top level of a meta class whose value is stored as one scalar; lists
 * and value classes are stored serialized. Every column of a table is queryable.
 *
 * The field class must match exactly: the Nullable… field classes extend the others but fit nullable columns only.
 *
 * @internal
 *
 * @phpstan-import-type Value from ValueFactory
 * @phpstan-import-type ColumnEntry from TableCollector
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
            'object', 'list', 'datetime' => null,
        };
    }

    /**
     * The field class and class of the type of every queryable field, for the generator; lists and value classes are
     * left out.
     *
     * @param array<string, Value> $values Values of a meta class by field.
     * @return array<string, array{class-string<Field>, class-string|null}>
     */
    public static function queryable(array $values): array
    {
        $fields = [];
        foreach ($values as $field => $value) {
            $expected = self::expected($value);
            if ($expected !== null) {
                $fields[$field] = $expected;
            }
        }

        return $fields;
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
            return sprintf('%s::$%s is a list or value class, stored serialized; only fields with one int, float, string, bool, enum, ID or post can be queried', $field->getClass(), $field->getField());
        }

        return self::compare($field, $expected);
    }

    /**
     * The field class and, for enums, the enum of a column of a table.
     *
     * @param ColumnEntry $column
     * @return array{class-string<Field>, class-string|null}
     */
    public static function column(array $column, bool $primaryKey): array
    {
        if ($primaryKey) {
            return [IntField::class, null];
        }
        $nullable = $column['nullable'];

        return match ($column['kind']) {
            'int' => [$nullable ? NullableIntField::class : IntField::class, null],
            'float' => [$nullable ? NullableFloatField::class : FloatField::class, null],
            'bool' => [$nullable ? NullableBoolField::class : BoolField::class, null],
            'string' => [$nullable ? NullableStringField::class : StringField::class, null],
            'enum' => [$nullable ? NullableEnumField::class : EnumField::class, $column['enum']],
            'datetime' => [$nullable ? NullableDateTimeField::class : DateTimeField::class, null],
        };
    }

    /**
     * Why a field object does not fit the column, or null if it does.
     *
     * @param ColumnEntry $column
     */
    public static function columnMismatch(Field $field, array $column, bool $primaryKey): ?string
    {
        return self::compare($field, self::column($column, $primaryKey));
    }

    /**
     * @param array{class-string<Field>, class-string|null} $expected
     */
    private static function compare(Field $field, array $expected): ?string
    {
        [$class, $typeClass] = $expected;
        if ($field::class !== $class) {
            return sprintf('%s::$%s needs %s, %s given', $field->getClass(), $field->getField(), self::describe($class, $typeClass), $field::class);
        }
        if ($field->getTypeClass() !== $typeClass) {
            return sprintf('%s::$%s needs %s, %s given', $field->getClass(), $field->getField(), self::describe($class, $typeClass), self::describe($field::class, $field->getTypeClass()));
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
