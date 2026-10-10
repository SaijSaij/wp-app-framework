<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Project;

/**
 * Kind of a consumer of the framework, as declared by the `type` of its composer.json.
 */
enum ProjectKind: string
{
    case Theme = 'wordpress-theme';
    case Plugin = 'wordpress-plugin';

    /**
     * Twig namespace that addresses the consumer's own templates exactly (`@theme/…`, `@plugin/…`).
     */
    public function getTemplateNamespace(): string
    {
        return match ($this) {
            self::Theme => 'theme',
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
            self::Theme => 'style.css',
            self::Plugin => $slug . '.php',
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
            self::Theme => 'functions.php',
            self::Plugin => $slug . '.php',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Theme => 'theme',
            self::Plugin => 'plugin',
        };
    }
}
