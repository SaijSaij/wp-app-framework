<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block\Markup;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Checks a value from block markup against an attribute schema as the build derives it (ValueSchemaBuilder):
 * `type` (one or several, including `null`), `enum`, `properties` with `required` and `items`. Unlike WordPress,
 * which ignores unknown properties and converts some types when sanitizing, unknown properties and other types
 * are errors: the markup is written by hand and should match what the editor stores.
 */
#[NoService]
final class AttributeValidator
{
    /**
     * @param array<array-key, mixed> $schema
     * @return list<string> Errors, each starting with `attribute "<path>"`.
     */
    public static function validate(mixed $value, array $schema, string $path): array
    {
        $actual = self::type($value);
        $types = isset($schema['type']) ? (array) $schema['type'] : [];
        if ($types !== [] && !self::matches($value, $actual, $types)) {
            return [sprintf('attribute "%s": expected %s, got %s.', $path, implode(' or ', array_map(self::text(...), $types)), $actual)];
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            return [sprintf(
                'attribute "%s": %s is not one of %s.',
                $path,
                self::encode($value),
                implode(', ', array_map(self::encode(...), $schema['enum'])),
            )];
        }

        if (!is_array($value)) {
            return [];
        }

        $errors = [];
        if (isset($schema['properties']) && is_array($schema['properties']) && ($actual === 'object' || $value === [])) {
            /** @var array<string, array<array-key, mixed>> $properties */
            $properties = $schema['properties'];
            foreach ($value as $key => $item) {
                if (!isset($properties[$key])) {
                    $errors[] = sprintf('attribute "%s": unknown property "%s".', $path, $key);
                    continue;
                }
                $errors = [...$errors, ...self::validate($item, $properties[$key], $path . '.' . $key)];
            }
            foreach ($properties as $key => $property) {
                if (($property['required'] ?? false) === true && !array_key_exists($key, $value)) {
                    $errors[] = sprintf('attribute "%s": missing required property "%s".', $path, $key);
                }
            }
        } elseif (isset($schema['items']) && is_array($schema['items'])) {
            /** @var array<array-key, mixed> $items */
            $items = $schema['items'];
            foreach ($value as $index => $item) {
                $errors = [...$errors, ...self::validate($item, $items, $path . '.' . $index)];
            }
        }

        return $errors;
    }

    /**
     * JSON type of a decoded value; an empty array may be an empty list or an empty object.
     */
    private static function type(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_string($value) => 'string',
            is_array($value) && array_is_list($value) => 'array',
            default => 'object',
        };
    }

    /**
     * @param array<mixed> $types
     */
    private static function matches(mixed $value, string $actual, array $types): bool
    {
        return in_array($actual, $types, true)
            || $actual === 'integer' && in_array('number', $types, true)
            || $value === [] && in_array('object', $types, true);
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : self::encode($value);
    }

    private static function encode(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
