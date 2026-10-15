<?php

declare(strict_types=1);

namespace Saij\WpAppFramework;

use LogicException;
use Saij\WpAppFramework\Project\ProjectKind;

/**
 * Boots the framework for an active child theme with code of its own and
 * provides its paths.
 *
 * A child theme with blocks, hooks or routes is a framework instance of its
 * own: its slug is get_stylesheet(), its release prefixes its own copy of the
 * framework, and it talks to its parent theme only through the public API
 * (`#[ThemeApi]`) and public template callables of the parent. A child theme
 * that only overrides templates of its parent (`overrides/{parent}/`) needs no
 * boot and no build.
 *
 * The instance is created only by boot() and handed to the container as a
 * predefined instance: services of the child theme can inject it.
 *
 * @api Boot classes
 */
final class ChildTheme
{
    /**
     * Manifest of a release build, relative to the child theme directory. Its presence selects release mode.
     *
     * @internal
     */
    public const string RELEASE_MANIFEST = Instance::RELEASE_MANIFEST;

    /**
     * @param non-empty-string $slug                get_stylesheet()
     * @param non-empty-string $parentSlug          get_template()
     * @param string           $directory           get_stylesheet_directory()
     * @param string           $directoryUri        get_stylesheet_directory_uri()
     * @param string           $parentDirectory     get_template_directory()
     * @param string           $parentDirectoryUri  get_template_directory_uri()
     * @param string           $frameworkDirectory  Filesystem path of the framework package; never derive URLs from it.
     */
    private function __construct(
        private readonly string $slug,
        private readonly string $parentSlug,
        private readonly string $directory,
        private readonly string $directoryUri,
        private readonly string $parentDirectory,
        private readonly string $parentDirectoryUri,
        private readonly string $frameworkDirectory,
    ) {}

    /**
     * Directory name of the child theme (get_stylesheet()); it names everything global of the framework instance.
     *
     * @return non-empty-string
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Directory name of the parent theme (get_template()).
     *
     * @return non-empty-string
     */
    public function getParentSlug(): string
    {
        return $this->parentSlug;
    }

    /**
     * Directory of the child theme, as returned by get_stylesheet_directory().
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * URI of the child theme directory, as returned by get_stylesheet_directory_uri().
     */
    public function getDirectoryUri(): string
    {
        return $this->directoryUri;
    }

    /**
     * Directory of the parent theme, as returned by get_template_directory().
     */
    public function getParentDirectory(): string
    {
        return $this->parentDirectory;
    }

    /**
     * URI of the parent theme directory, as returned by get_template_directory_uri().
     */
    public function getParentDirectoryUri(): string
    {
        return $this->parentDirectoryUri;
    }

    /**
     * Filesystem path of the framework package; never derive URLs from it.
     */
    public function getFrameworkDirectory(): string
    {
        return $this->frameworkDirectory;
    }

    /**
     * Boots the framework. Called once from the child theme's functions.php,
     * which WordPress loads before the parent theme's.
     *
     * Release mode applies if the child theme contains generated/manifest.php
     * (created only by the release build). Caches live in
     * wp-content/cache/{stylesheet}. Templates are looked up in the child
     * theme's `src/`, then in the framework. At its end, boot() fires the action
     * `wp_app_framework/init/{stylesheet}`.
     */
    public static function boot(): void
    {
        $slug = get_stylesheet();
        $parentSlug = get_template();
        if ($slug === '' || $parentSlug === '' || $slug === $parentSlug) {
            throw new LogicException(sprintf(
                'ChildTheme::boot() needs an active child theme, but the active theme "%s" has no parent theme; a theme boots with Theme::boot().',
                $slug,
            ));
        }

        $childTheme = new self(
            $slug,
            $parentSlug,
            get_stylesheet_directory(),
            get_stylesheet_directory_uri(),
            get_template_directory(),
            get_template_directory_uri(),
            dirname(__DIR__),
        );

        new Instance(
            ProjectKind::ChildTheme,
            $slug,
            $childTheme->directory,
            $childTheme->directoryUri,
            $childTheme,
            static fn(): array => ['overrides' => [], 'cacheKey' => null],
            $parentSlug,
        )->boot();
    }
}
