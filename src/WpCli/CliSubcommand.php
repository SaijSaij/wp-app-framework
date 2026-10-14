<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\WpCli;

use Attribute;

/**
 * Registers a public method of a `#[CliCommand]` service as a WP-CLI command,
 * `wp {namespace} {command} {name}`. The method returns `void`.
 *
 * A parameter typed `CliOutput` receives the output, parameters typed with a
 * service candidate are injected, all others are marked `#[Argument]`
 * (positional argument) or `#[Option]` (option, a flag for `bool`).
 *
 * The description is the one-line summary in `wp help` (at most 80
 * characters); the long description follows the options in the help, e.g. an
 * `## EXAMPLES` section.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class CliSubcommand
{
    public function __construct(
        public string $name,
        public string $description,
        public string $longDescription = '',
    ) {}
}
