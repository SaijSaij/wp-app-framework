<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Table;

use BackedEnum;
use DateTimeInterface;
use LogicException;
use RuntimeException;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\Field\DateTimeField;

/**
 * Converts the values of columns between the database and PHP. wpdb returns every value as a string (null for NULL);
 * decode() turns it into the raw value the ValueFactory expects, encode() a property into the value and format of
 * wpdb. Floats are written as strings with every digit (wpdb's %f keeps six decimals), booleans as 1 and 0, enums as
 * the value of their case and times in UTC.
 *
 * @internal
 *
 * @phpstan-import-type ColumnEntry from TableCollector
 */
#[NoService]
final class RowCodec
{
    /**
     * @param ColumnEntry $column
     * @param string      $table  The full table name, for errors.
     */
    public static function decode(array $column, mixed $stored, string $table, int|string $id): int|float|string|bool|null
    {
        if ($stored === null) {
            return null;
        }
        $raw = is_scalar($stored) ? (string) $stored : null;
        $integer = $column['kind'] === 'int' || ($column['kind'] === 'enum' && $column['sql'] === 'bigint(20)');
        $value = match (true) {
            $raw === null => null,
            $integer => preg_match('/^-?\d+$/', $raw) === 1 ? (int) $raw : null,
            $column['kind'] === 'float' => is_numeric($raw) ? (float) $raw : null,
            $column['kind'] === 'bool' => match ($raw) {
                '0' => false,
                '1' => true,
                default => null,
            },
            $column['kind'] === 'datetime' => preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw) === 1 && $raw !== '0000-00-00 00:00:00' ? $raw : null,
            default => $raw,
        };
        if ($value === null) {
            throw new RuntimeException(sprintf(
                'The column %s of the row %s in the table %s holds %s, which is no valid %s%s.',
                $column['column'],
                (string) $id,
                $table,
                var_export($stored, true),
                $column['kind'],
                $column['kind'] === 'datetime' ? ' (the zero date stands for a time that was never set, e.g. in a column added to rows that existed already)' : '',
            ));
        }

        return $value;
    }

    /**
     * @param ColumnEntry $column
     * @param string      $location The property, for errors.
     * @return array{int|string|null, '%d'|'%s'}
     */
    public static function encode(array $column, mixed $value, string $location): array
    {
        if ($value === null) {
            return [null, '%s'];
        }

        return match (true) {
            $value instanceof BackedEnum => [$value->value, is_int($value->value) ? '%d' : '%s'],
            $value instanceof DateTimeInterface => [DateTimeField::utc($value), '%s'],
            is_bool($value) => [$value ? 1 : 0, '%d'],
            is_int($value) => [$value, '%d'],
            is_float($value) => [self::float($value, $location), '%s'],
            is_string($value) => [$value, '%s'],
            default => throw new LogicException(sprintf('%s holds a %s, which the column %s cannot store.', $location, get_debug_type($value), $column['column'])),
        };
    }

    /**
     * A float with every digit, so that it reads back as the same float.
     */
    public static function float(float $value, string $location): string
    {
        if (!is_finite($value)) {
            throw new RuntimeException(sprintf('%s is %s; the database stores finite floats only.', $location, var_export($value, true)));
        }

        return var_export($value, true);
    }
}
