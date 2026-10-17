<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Manifest\Discovery;

use ReflectionClass;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Finds the scanned classes that build on a class outside the project: neither in the scanned sources, nor found by
 * the Composer autoloader of the project, nor part of PHP – typically a class of WordPress or of another plugin.
 * Such a class cannot be loaded without WordPress, so the build never loads it, whether WordPress is loaded or not.
 */
#[NoService]
final class ExternalParents
{
    /** @var array<string, ClassDeclaration> Lowercase name => declaration. */
    private array $declarations = [];

    /** @var array<string, string|false> Lowercase name => outside class it builds on, or false. */
    private array $external = [];

    /** @var list<object>|null */
    private ?array $loaders = null;

    /**
     * @param string|null $projectDirectory Only Composer autoloaders whose vendor directory lies in this directory
     *                                      count; null for builds of fixtures, which use every registered one.
     */
    public function __construct(private readonly ?string $projectDirectory) {}

    public function add(string $name, ClassDeclaration $declaration): void
    {
        $this->declarations[strtolower($name)] = $declaration;
    }

    /**
     * The outside class the scanned class builds on, directly or through other scanned classes, or null.
     */
    public function of(string $name): ?string
    {
        $key = strtolower($name);
        if (isset($this->external[$key])) {
            return $this->external[$key] === false ? null : $this->external[$key];
        }
        // Against cycles, which PHP reports when the class is loaded.
        $this->external[$key] = false;

        foreach (($this->declarations[$key] ?? null)?->getParents() ?? [] as $parent) {
            $outside = isset($this->declarations[strtolower($parent)]) ? $this->of($parent) : ($this->isKnown($parent) ? null : $parent);
            if ($outside !== null) {
                $this->external[$key] = $outside;

                return $outside;
            }
        }

        return null;
    }

    /**
     * Whether a class outside the scanned sources can be loaded without WordPress: a class of PHP or its extensions,
     * or one the Composer autoloader of the project finds. Nothing is loaded.
     */
    private function isKnown(string $name): bool
    {
        if (class_exists($name, false) || interface_exists($name, false) || trait_exists($name, false)) {
            /** @var class-string $name Exists. */
            if (new ReflectionClass($name)->isInternal()) {
                return true;
            }
        }

        foreach ($this->loaders() as $loader) {
            if (method_exists($loader, 'findFile') && $loader->findFile($name) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * The registered Composer autoloaders of the project. The class loader is found through the registered autoload
     * functions, because a release build prefixes it (`vendor-prefixed/composer/ClassLoader.php`).
     *
     * @return list<object>
     */
    private function loaders(): array
    {
        if ($this->loaders !== null) {
            return $this->loaders;
        }

        $project = $this->projectDirectory === null ? null : rtrim($this->projectDirectory, '/') . '/';
        $loaders = [];
        foreach (spl_autoload_functions() as $function) {
            if (!is_array($function) || !is_object($function[0]) || !method_exists($function[0], 'getRegisteredLoaders')) {
                continue;
            }
            $registered = $function[0]::getRegisteredLoaders();
            foreach (is_array($registered) ? $registered : [] as $vendorDirectory => $loader) {
                if (is_object($loader) && ($project === null || str_starts_with((string) $vendorDirectory . '/', $project))) {
                    $loaders[spl_object_id($loader)] = $loader;
                }
            }
        }

        return $this->loaders = array_values($loaders);
    }
}
