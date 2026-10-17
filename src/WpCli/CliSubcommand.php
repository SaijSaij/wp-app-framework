<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\WpCli;

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
 *
 * @api Attributes/WP-CLI
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class CliSubcommand
{
    /**
     * @param string $name            The name of the subcommand, `wp {namespace} {command} {name}`; starts with a
     *                                lowercase letter and contains lowercase letters, digits and "-"; unique within its
     *                                command.
     * @param string $description     The one-line summary in `wp help`: not empty, no line break, at most 80
     *                                characters.
     * @param string $longDescription Text the help shows after the options, e.g. an `## EXAMPLES` section; may span
     *                                several lines.
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $longDescription = '',
    ) {}
}
