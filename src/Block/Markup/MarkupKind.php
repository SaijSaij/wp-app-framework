<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block\Markup;

/**
 * Kinds of theme files with block markup, by the directory WordPress reads them from.
 */
enum MarkupKind: string
{
    case Template = 'templates';
    case Part = 'parts';
    case Pattern = 'patterns';

    public function getDirectory(): string
    {
        return $this->value;
    }

    /**
     * Pattern for the file names WordPress reads below the directory: block templates and template parts as
     * `*.html` in any case (`_get_block_templates_paths()`), patterns as `*.php` (`WP_Theme::get_block_patterns()`).
     */
    public function getFilePattern(): string
    {
        return match ($this) {
            self::Template, self::Part => '/\.html$/i',
            self::Pattern => '/\.php$/',
        };
    }
}
