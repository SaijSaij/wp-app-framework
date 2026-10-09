<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Manifest\DevCache;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Saij\WpAppFramework\DependencyInjection\NoService;

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

    /**
     * @param list<ReflectionAttribute<object>> $attributes
     */
    private static function attributes(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $attribute) {
            $parts[] = $attribute->getName() . var_export($attribute->getArguments(), true);
        }

        return implode(';', $parts);
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
