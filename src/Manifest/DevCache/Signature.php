<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Manifest\DevCache;

use Closure;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionFunction;
use ReflectionMethod;
use Saij\WpAppFramework\Block\Labeled;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Throwable;
use UnitEnum;

/**
 * Hash of everything in a class that can affect the manifest: kind, modifiers,
 * attributes, parents, interfaces, traits and the signatures of the constructor
 * and public methods – but not method bodies (same idea as Symfony's
 * ReflectionClassResource).
 */
#[NoService]
final class Signature
{
    /**
     * @param ReflectionClass<object> $class
     */
    public static function of(ReflectionClass $class): string
    {
        $parts = [
            $class->getName(),
            (string) $class->getModifiers(),
            $class->isInterface() ? 'interface' : ($class->isTrait() ? 'trait' : ($class->isEnum() ? 'enum' : 'class')),
            self::attributes($class->getAttributes()),
            (string) ($class->getParentClass() ? $class->getParentClass()->getName() : ''),
            implode(',', self::sorted($class->getInterfaceNames())),
            implode(',', self::sorted($class->getTraitNames())),
        ];

        // Enum cases are constants, not methods; they define the values of schemas.
        if ($class->isEnum()) {
            /** @var class-string<\UnitEnum> $enum */
            $enum = $class->getName();
            foreach (new ReflectionEnum($enum)->getCases() as $case) {
                $parts[] = 'case ' . $case->getName() . '=' . self::describe($case instanceof ReflectionEnumBackedCase ? $case->getBackingValue() : null);
                // The editor labels come from a method body, which the signature otherwise ignores.
                if ($class->implementsInterface(Labeled::class)) {
                    $parts[] = 'label ' . self::label($case->getValue());
                }
            }
        }

        $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);
        $constructor = $class->getConstructor();
        if ($constructor !== null && !$constructor->isPublic()) {
            $methods[] = $constructor;
        }

        foreach ($methods as $method) {
            // The reflection string contains the signature; its "@@ file start-end" lines change with every body edit.
            $parts[] = preg_replace('/^\s*@@.*$/m', '', (string) $method);
            $parts[] = self::attributes($method->getAttributes());
            foreach ($method->getParameters() as $parameter) {
                $parts[] = self::attributes($parameter->getAttributes());
            }
        }

        return hash('xxh128', implode("\n", $parts));
    }

    /**
     * Absolute paths of the files declaring the class, its parents, interfaces and traits.
     *
     * @param ReflectionClass<object> $class
     * @return list<string>
     */
    public static function files(ReflectionClass $class): array
    {
        $files = [];
        $pending = [$class];

        while ($pending !== []) {
            $current = array_shift($pending);
            $file = $current->getFileName();
            if ($file !== false) {
                $files[$file] = true;
            }

            $parent = $current->getParentClass();
            if ($parent !== false) {
                $pending[] = $parent;
            }
            foreach ($current->getInterfaces() as $interface) {
                $pending[] = $interface;
            }
            foreach ($current->getTraits() as $trait) {
                $pending[] = $trait;
            }
        }

        $files = array_keys($files);
        sort($files, SORT_STRING);

        return $files;
    }

    private static function label(UnitEnum $case): string
    {
        try {
            return $case instanceof Labeled ? var_export($case->label(), true) : '';
        } catch (Throwable $exception) {
            // The build reports the error; a different message still means a different signature.
            return 'error ' . $exception->getMessage();
        }
    }

    /**
     * @param list<ReflectionAttribute<object>> $attributes
     */
    private static function attributes(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $attribute) {
            $parts[] = $attribute->getName() . self::describe($attribute->getArguments());
        }

        return implode(';', $parts);
    }

    /**
     * Describes an attribute argument. var_export() would reduce every closure to
     * an empty Closure::__set_state(), so new Callback(self::a(...)) and
     * new Callback(self::b(...)) would not differ.
     */
    private static function describe(mixed $value): string
    {
        if ($value instanceof Closure) {
            $function = new ReflectionFunction($value);

            return 'closure(' . ($function->getClosureScopeClass()?->getName() ?? '') . '::' . $function->getName() . ')';
        }
        if ($value instanceof UnitEnum) {
            return $value::class . '::' . $value->name;
        }
        if (is_object($value)) {
            // The array cast includes private and protected properties.
            return $value::class . self::describe((array) $value);
        }
        if (is_array($value)) {
            $items = [];
            foreach ($value as $key => $item) {
                $items[] = var_export($key, true) . '=>' . self::describe($item);
            }

            return '[' . implode(',', $items) . ']';
        }

        return var_export($value, true);
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    private static function sorted(array $names): array
    {
        sort($names, SORT_STRING);

        return $names;
    }
}
