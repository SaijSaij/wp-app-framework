<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\WpCli;

use Attribute;

/**
 * Marks a service as a group of WP-CLI commands: its `#[CliSubcommand]` methods
 * become `wp {namespace} {name} {subcommand}`, where the namespace is the one of
 * the theme or plugin (`extra.wp-app-framework.namespace`).
 *
 * The name starts with a lowercase letter and contains lowercase letters, digits
 * and "-". The description is the one-line summary in `wp help` (at most 80
 * characters).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class CliCommand
{
    public function __construct(
        public string $name,
        public string $description,
    ) {}
}
