<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\WpCli;

use Attribute;

/**
 * Marks a parameter of a `#[CliSubcommand]` method as positional argument,
 * `<name>` with the parameter name in kebab-case. A parameter with a default
 * value is optional, a variadic parameter takes all remaining arguments.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Argument
{
    public function __construct(
        public string $description = '',
    ) {}
}
