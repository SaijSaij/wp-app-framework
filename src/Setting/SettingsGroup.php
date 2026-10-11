<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Setting;

use Attribute;

/**
 * Declares a group of settings of a theme, child theme or plugin.
 *
 * The class is `readonly` and declares each setting as a public promoted
 * constructor parameter with a default, like a block attribute class: the
 * types and markers of values (`#[ListOf]`, `#[Url]`, `#[Label]`, `#[Hidden]`,
 * `#[Image]` for an attachment ID) derive the schema and the form. The framework stores the group in the option
 * `{slug}_{name}` (slug with "_" instead of "-"), edits it on the settings page
 * of the theme or plugin and injects it into the constructor of services that
 * declare a parameter of its type; templates read it as `settings.{name}`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class SettingsGroup
{
    /**
     * @param string $name        Name of the group: lowercase letters, digits and "_", starting with a letter
     *                            (e.g. "general"); part of the option name and the key in templates.
     * @param string $title       Title of the group on the settings page (the tab if there are several groups).
     * @param string $description Optional text above the form of the group.
     * @param int    $position    Order of the groups on the settings page: lower first, then by name.
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $description = '',
        public int $position = 10,
    ) {}
}
