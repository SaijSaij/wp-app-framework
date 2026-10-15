<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\WpCli;

use Attribute;

/**
 * Marks a parameter of a `#[CliSubcommand]` method as option,
 * `--name=<name>` with the parameter name in kebab-case. A `bool` parameter is
 * a flag, `--name`, and defaults to false. A parameter without default value is
 * a required option.
 *
 * @api Attributes/WP-CLI
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Option
{
    /**
     * @param string $description The description of the option in the help; empty for none.
     */
    public function __construct(
        public string $description = '',
    ) {}
}
