<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Twig;

use Twig\Loader\FilesystemLoader;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Filesystem loader whose cache keys start with the slug of the framework
 * instance and the signature of its template functions and filters.
 *
 * Twig names the class of a compiled template after the cache key (the path
 * relative to the root path) and the environment options. In development mode
 * several instances share the unprefixed Twig classes, so without the slug a
 * plugin and the theme would share the compiled class of templates with the
 * same relative path, and Twig would render the one loaded first.
 *
 * A compiled template also contains the class and method of every template
 * function and filter it calls, but Twig's own key covers only the class names
 * of extensions, and the framework registers its callables without one. The
 * signature puts them into the key: when a callable moves to another class or
 * method, e.g. after the framework or a consumer renamed its namespace, the
 * templates are compiled anew instead of calling a class that no longer exists.
 */
#[NoService]
final class InstanceLoader extends FilesystemLoader
{
    /**
     * @param non-empty-string $slug
     * @param non-empty-string $callables Signature of the template functions and filters (TemplateRenderer).
     */
    public function __construct(
        private readonly string $slug,
        string $rootPath,
        private readonly string $callables,
    ) {
        parent::__construct([], $rootPath);
    }

    public function getCacheKey(string $name): string
    {
        return $this->slug . ':' . $this->callables . ':' . parent::getCacheKey($name);
    }
}
