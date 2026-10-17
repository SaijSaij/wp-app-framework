<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\WpCli;

use Attribute;

/**
 * Marks a service as a group of WP-CLI commands: its `#[CliSubcommand]` methods
 * become `wp {namespace} {name} {subcommand}`, where the namespace is the one of
 * the theme or plugin (`extra.wp-app-framework.namespace`).
 *
 * The name starts with a lowercase letter and contains lowercase letters, digits
 * and "-". The description is the one-line summary in `wp help` (at most 80
 * characters).
 *
 * @api Attributes/WP-CLI
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class CliCommand
{
    /**
     * @param string $name        The name of the command group, `wp {namespace} {name}`; starts with a lowercase letter
     *                            and contains lowercase letters, digits and "-"; unique within the theme or plugin.
     * @param string $description The one-line summary in `wp help`: not empty, no line break, at most 80 characters.
     */
    public function __construct(
        public string $name,
        public string $description,
    ) {}
}
