<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Support;

use LogicException;
use ReflectionClass;
use WebCodingCat\WpAppFramework\Manifest\Discovery\ClassDiscovery;
use WebCodingCat\WpAppFramework\Manifest\Discovery\Source;

final class Reflection
{
    /**
     * The classes of a fixture source, loaded like the manifest builder does.
     *
     * @return array<class-string, ReflectionClass<object>>
     */
    public static function classes(Source $source): array
    {
        $classes = [];
        foreach (ClassDiscovery::classes($source) as $name) {
            if (!class_exists($name) && !interface_exists($name) && !trait_exists($name) && !enum_exists($name)) {
                throw new LogicException(sprintf('Fixture class %s cannot be loaded.', $name));
            }
            $classes[$name] = new ReflectionClass($name);
        }

        return $classes;
    }
}
