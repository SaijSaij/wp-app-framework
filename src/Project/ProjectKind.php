<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Project;

/**
 * Kind of a consumer of the framework. The build derives it from the `type` of
 * its composer.json and, for themes, the `Template:` header of style.css, which
 * only child themes declare.
 */
enum ProjectKind: string
{
    case Theme = 'theme';
    case ChildTheme = 'child-theme';
    case Plugin = 'plugin';

    /**
     * The composer.json `type` of this kind.
     */
    public function getComposerType(): string
    {
        return match ($this) {
            self::Theme, self::ChildTheme => 'wordpress-theme',
            self::Plugin => 'wordpress-plugin',
        };
    }

    /**
     * Twig namespace that addresses the consumer's own templates exactly (`@theme/…`, `@plugin/…`).
     */
    public function getTemplateNamespace(): string
    {
        return match ($this) {
            self::Theme, self::ChildTheme => 'theme',
            self::Plugin => 'plugin',
        };
    }

    /**
     * The file the release reads the version from, relative to the project.
     *
     * @param non-empty-string $slug
     * @return non-empty-string
     */
    public function getHeaderFile(string $slug): string
    {
        return match ($this) {
            self::Theme, self::ChildTheme => 'style.css',
            self::Plugin => $slug . '.php',
        };
    }

    /**
     * The header in the header file that names the theme or plugin.
     */
    public function getNameHeader(): string
    {
        return match ($this) {
            self::Theme, self::ChildTheme => 'Theme Name',
            self::Plugin => 'Plugin Name',
        };
    }

    /**
     * The file that boots the framework; Strauss must rewrite its references to the framework.
     *
     * @param non-empty-string $slug
     * @return non-empty-string
     */
    public function getBootFile(string $slug): string
    {
        return match ($this) {
            self::Theme, self::ChildTheme => 'functions.php',
            self::Plugin => $slug . '.php',
        };
    }

    /**
     * Whether the consumer may offer a public API and public template callables to other consumers.
     */
    public function isProvider(): bool
    {
        return $this !== self::ChildTheme;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Theme => 'theme',
            self::ChildTheme => 'child theme',
            self::Plugin => 'plugin',
        };
    }
}
