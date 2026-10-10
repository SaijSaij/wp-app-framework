<?php

declare(strict_types=1);

namespace Saij\WpAppFramework;

use LogicException;
use Saij\WpAppFramework\Project\ProjectKind;
use Saij\WpAppFramework\Twig\TemplateRenderer;

/**
 * Boots the framework for the active theme and provides its paths.
 *
 * The instance is created only by boot() and handed to the container as a
 * predefined instance: the container never builds it, but services of a theme
 * can inject it to read the paths.
 */
final class Theme
{
    /** Manifest of a release build, relative to the theme directory. Its presence selects release mode. */
    public const string RELEASE_MANIFEST = Instance::RELEASE_MANIFEST;

    /**
     * @param string $templateDirectory     get_template_directory()
     * @param string $templateDirectoryUri  get_template_directory_uri()
     * @param string $stylesheetDirectory   get_stylesheet_directory() – the child theme, if active
     * @param string $stylesheetDirectoryUri get_stylesheet_directory_uri()
     * @param string $frameworkDirectory    Filesystem path of the framework package; never derive URLs from it.
     */
    private function __construct(
        private readonly string $templateDirectory,
        private readonly string $templateDirectoryUri,
        private readonly string $stylesheetDirectory,
        private readonly string $stylesheetDirectoryUri,
        private readonly string $frameworkDirectory,
    ) {}

    /**
     * Directory of the parent theme, as returned by get_template_directory().
     */
    public function getTemplateDirectory(): string
    {
        return $this->templateDirectory;
    }

    /**
     * URI of the parent theme directory, as returned by get_template_directory_uri().
     */
    public function getTemplateDirectoryUri(): string
    {
        return $this->templateDirectoryUri;
    }

    /**
     * Directory of the active (child) theme, as returned by get_stylesheet_directory().
     */
    public function getStylesheetDirectory(): string
    {
        return $this->stylesheetDirectory;
    }

    /**
     * URI of the active (child) theme directory, as returned by get_stylesheet_directory_uri().
     */
    public function getStylesheetDirectoryUri(): string
    {
        return $this->stylesheetDirectoryUri;
    }

    /**
     * Filesystem path of the framework package; never derive URLs from it.
     */
    public function getFrameworkDirectory(): string
    {
        return $this->frameworkDirectory;
    }

    /**
     * Boots the framework. Called once from the theme's functions.php.
     *
     * Release mode applies if the theme contains generated/manifest.php (created
     * only by the release build). Otherwise development mode builds the manifest
     * by reflection and caches it in wp-content/cache/{template}/manifest.
     * Compiled Twig templates are cached in wp-content/cache/{template}/twig, in
     * release mode below the template version of the release. At its end, boot()
     * fires the action `wp_app_framework/init/{template}`.
     */
    public static function boot(): void
    {
        $theme = new self(
            get_template_directory(),
            get_template_directory_uri(),
            get_stylesheet_directory(),
            get_stylesheet_directory_uri(),
            dirname(__DIR__),
        );

        $slug = get_template();
        if ($slug === '') {
            throw new LogicException('get_template() is empty; Theme::boot() must be called from the functions.php of the active theme.');
        }

        new Instance(
            ProjectKind::Theme,
            $slug,
            $theme->templateDirectory,
            $theme->templateDirectoryUri,
            $theme,
            static fn(): array => [
                'overrides' => self::templateOverrides($theme->templateDirectory, $theme->stylesheetDirectory),
                'cacheKey' => null,
            ],
        )->boot();
    }

    /**
     * Template directories searched before the theme's own `src/`: the child
     * theme's `src/`, if a child theme is active and has one.
     *
     * @internal
     *
     * @return list<string>
     */
    public static function templateOverrides(string $templateDirectory, string $stylesheetDirectory): array
    {
        // A child theme is optional and needs no templates of its own.
        $childTemplates = $stylesheetDirectory . '/' . TemplateRenderer::TEMPLATE_DIRECTORY;

        return $stylesheetDirectory !== $templateDirectory && is_dir($childTemplates) ? [$childTemplates] : [];
    }
}
