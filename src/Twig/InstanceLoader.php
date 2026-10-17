<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Twig;

use Twig\Loader\FilesystemLoader;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Filesystem loader whose cache keys start with the slug of the framework
 * instance.
 *
 * Twig names the class of a compiled template after the cache key (the path
 * relative to the root path) and the environment options. In development mode
 * several instances share the unprefixed Twig classes, so without the slug a
 * plugin and the theme would share the compiled class of templates with the
 * same relative path, and Twig would render the one loaded first.
 */
#[NoService]
final class InstanceLoader extends FilesystemLoader
{
    /**
     * @param non-empty-string $slug
     */
    public function __construct(
        private readonly string $slug,
        string $rootPath,
    ) {
        parent::__construct([], $rootPath);
    }

    public function getCacheKey(string $name): string
    {
        return $this->slug . ':' . parent::getCacheKey($name);
    }
}
