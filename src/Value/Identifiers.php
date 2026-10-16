<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Value;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Fills in the identifiers (`#[Identifier]`) of list items in a stored value, e.g. a settings group before it is
 * saved.
 *
 * @internal
 */
#[NoService]
final class Identifiers
{
    /**
     * Fills in the identifiers of list items at the given paths ("schemes.*.id", "rows.*.cells.*.key"; `*` for list
     * items): an item without a valid identifier, or with the identifier of an item before it in the same list, gets
     * a new one. Values that are no list items are left as they are.
     *
     * @param list<string> $paths
     */
    public static function fill(mixed $value, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = self::fillPath($value, explode('.', $path));
        }

        return $value;
    }

    /**
     * @param list<string> $path
     */
    private static function fillPath(mixed $value, array $path): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $segment = array_shift($path);
        if ($segment === '*' && count($path) === 1) {
            return self::fillList($value, $path[0]);
        }
        foreach ($value as $key => $item) {
            if ($segment === '*' ? is_int($key) : $key === $segment) {
                $value[$key] = self::fillPath($item, $path);
            }
        }

        return $value;
    }

    /**
     * @param array<mixed> $list
     * @return array<mixed>
     */
    private static function fillList(array $list, string $key): array
    {
        if (!array_is_list($list)) {
            return $list;
        }
        // A new identifier repeats neither an item before it nor a valid identifier after it.
        $existing = self::identifiers($list, $key);
        $taken = [];
        foreach ($list as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $identifier = $item[$key] ?? '';
            if (!is_string($identifier) || $identifier === '' || !Identifier::isIdentifier($identifier) || in_array($identifier, $taken, true)) {
                $identifier = Identifier::generate([...$existing, ...$taken]);
                $item[$key] = $identifier;
                $list[$index] = $item;
            }
            $taken[] = $identifier;
        }

        return $list;
    }

    /**
     * The valid identifiers of a list, so a new one never repeats an identifier of a later item.
     *
     * @param list<mixed> $list
     * @return list<string>
     */
    private static function identifiers(array $list, string $key): array
    {
        $identifiers = [];
        foreach ($list as $item) {
            if (is_array($item) && is_string($item[$key] ?? null) && $item[$key] !== '' && Identifier::isIdentifier($item[$key])) {
                $identifiers[] = $item[$key];
            }
        }

        return $identifiers;
    }
}
