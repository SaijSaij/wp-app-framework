<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Support;

use Composer\Autoload\ClassLoader;

/**
 * Gives a temporary project the Composer autoloader of a real consumer: in its own vendor directory, with the
 * framework's dependencies. The build resolves parent classes outside the sources with it.
 */
final class ProjectLoader
{
    public static function register(string $project): ClassLoader
    {
        $framework = ClassLoader::getRegisteredLoaders()[(string) realpath(__DIR__ . '/../../vendor')];
        $loader = new ClassLoader($project . '/vendor');
        $loader->addClassMap($framework->getClassMap());
        foreach ($framework->getPrefixesPsr4() as $prefix => $directories) {
            $loader->addPsr4($prefix, $directories);
        }
        $loader->register();

        return $loader;
    }
}
