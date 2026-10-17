<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Project;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Prefixes of names derived from the slug or namespace of a theme or plugin; used at build and run time, so they do
 * not live in the build class ComposerProject.
 *
 * @internal
 */
#[NoService]
final class NamePrefix
{
    /**
     * Prefix of the public template functions and filters of a plugin or theme: the slug
     * with underscores instead of hyphens, followed by an underscore
     * (`plugin-dev-test` → `plugin_dev_test_`).
     *
     * @param non-empty-string $slug
     * @return non-empty-string
     */
    public static function template(string $slug): string
    {
        return str_replace('-', '_', $slug) . '_';
    }

    /**
     * Prefix of the data names of a namespace – settings options, post types, taxonomies and meta keys: the
     * namespace with underscores instead of hyphens, followed by an underscore (`dev-test` → `dev_test_`).
     *
     * @param non-empty-string $namespace
     * @return non-empty-string
     */
    public static function data(string $namespace): string
    {
        return str_replace('-', '_', $namespace) . '_';
    }
}
