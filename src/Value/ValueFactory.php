<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Value;

use BackedEnum;
use InvalidArgumentException;
use LogicException;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * The only way from raw values to typed values: block attribute objects, value
 * objects, enums and lists. Driven by the value descriptions of the manifest,
 * without reflection.
 *
 * Values are expected to be validated against their schema already (WordPress
 * does that for block attributes and REST arguments); a value that still does
 * not match its type is an error, never coerced silently.
 *
 * @internal
 *
 * @phpstan-type ClassValue array{type: 'enum'|'object', class: class-string, nullable: bool}
 * @phpstan-type Value array{type: 'int'|'float'|'string'|'bool', nullable: bool}
 *     |ClassValue
 *     |array{type: 'list', items: ClassValue, nullable: bool}
 * @phpstan-type ValueField array{value: Value, required: bool}
 */
#[NoService]
final readonly class ValueFactory
{
    /**
     * @param array<class-string, array<string, ValueField>> $classes Value classes: constructor parameter => field.
     */
    public function __construct(private array $classes) {}

    /**
     * Creates a block attribute object from the attributes WordPress passes to
     * the render callback. WordPress validates block attributes before
     * rendering but does not sanitize them (e.g. "5" is a valid integer), so
     * each attribute of the class is sanitized against its schema first.
     * Attributes of the class that are missing use the constructor default;
     * attributes of block supports (className, …) are ignored.
     *
     * @template T of object
     * @param class-string<T>                     $class
     * @param array<string, mixed>                $attributes
     * @param array<string, array<string, mixed>> $schemas    Attribute schemas of the block type.
     * @return T
     */
    public function createAttributes(string $class, array $attributes, array $schemas): object
    {
        $data = [];
        foreach (array_keys($this->fields($class)) as $name) {
            if (array_key_exists($name, $attributes)) {
                $data[$name] = rest_sanitize_value_from_schema($attributes[$name], $schemas[$name], $name);
            }
        }

        return $this->createObject($class, $data, '');
    }

    /**
     * Converts a value according to its description.
     *
     * @param Value  $value
     * @param string $path  Location of the value for error messages, e.g. "buttons[1].url".
     */
    public function create(array $value, mixed $raw, string $path): mixed
    {
        if ($raw === null) {
            if ($value['nullable']) {
                return null;
            }
            throw self::mismatch($path, $value, $raw);
        }

        return match ($value['type']) {
            'int' => is_int($raw) ? $raw : throw self::mismatch($path, $value, $raw),
            // JSON does not distinguish 1 from 1.0.
            'float' => is_float($raw) || is_int($raw) ? (float) $raw : throw self::mismatch($path, $value, $raw),
            'string' => is_string($raw) ? $raw : throw self::mismatch($path, $value, $raw),
            'bool' => is_bool($raw) ? $raw : throw self::mismatch($path, $value, $raw),
            'enum' => $this->createEnum($value['class'], $raw, $path),
            'object' => is_array($raw) ? $this->createObject($value['class'], $raw, $path) : throw self::mismatch($path, $value, $raw),
            'list' => is_array($raw) && array_is_list($raw)
                ? array_map(
                    fn(int $index, mixed $item): mixed => $this->create($value['items'], $item, sprintf('%s[%d]', $path, $index)),
                    array_keys($raw),
                    $raw,
                )
                : throw self::mismatch($path, $value, $raw),
        };
    }

    /**
     * @template T of object
     * @param class-string<T>      $class
     * @param array<mixed, mixed>  $data
     * @return T
     */
    private function createObject(string $class, array $data, string $path): object
    {
        $named = [];
        foreach ($this->fields($class) as $name => $field) {
            $fieldPath = $path === '' ? $name : $path . '.' . $name;
            if (!array_key_exists($name, $data)) {
                if ($field['required']) {
                    throw new InvalidArgumentException(sprintf('Value "%s" for %s::$%s is missing.', $fieldPath, $class, $name));
                }
                continue;
            }
            $named[$name] = $this->create($field['value'], $data[$name], $fieldPath);
        }

        return new $class(...$named);
    }

    /**
     * @param class-string $class
     */
    private function createEnum(string $class, mixed $raw, string $path): BackedEnum
    {
        /** @var class-string<BackedEnum> $class */
        $case = is_int($raw) || is_string($raw) ? $class::tryFrom($raw) : null;
        if ($case === null) {
            throw new InvalidArgumentException(sprintf('Value "%s" must be a value of %s, %s given.', $path, $class, var_export($raw, true)));
        }

        return $case;
    }

    /**
     * @param class-string $class
     * @return array<string, ValueField>
     */
    private function fields(string $class): array
    {
        return $this->classes[$class] ?? throw new LogicException(sprintf(
            'Value class %s is not in the manifest; rebuild the manifest after code changes.',
            $class,
        ));
    }

    /**
     * @param Value $value
     */
    private static function mismatch(string $path, array $value, mixed $raw): InvalidArgumentException
    {
        $expected = match ($value['type']) {
            'object' => 'an object of ' . $value['class'],
            'list' => 'a list',
            'enum' => 'a value of ' . $value['class'],
            default => $value['type'],
        };

        return new InvalidArgumentException(sprintf(
            'Value "%s" must be %s%s, %s given.',
            $path,
            $expected,
            $value['nullable'] ? ' or null' : '',
            get_debug_type($raw),
        ));
    }
}
