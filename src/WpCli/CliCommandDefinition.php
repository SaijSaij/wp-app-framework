<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\WpCli;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * A validated `#[CliCommand]` service with its `#[CliSubcommand]` methods.
 *
 * @phpstan-import-type Value from \WebCodingCat\WpAppFramework\Value\ValueFactory
 * @phpstan-type CliArgument array{param: string, kind: 'service', class: class-string}
 *     |array{param: string, kind: 'output'}
 *     |array{param: string, kind: 'positional', name: non-empty-string, index: int, variadic: bool, value: Value, schema: array<string, mixed>, default?: mixed}
 *     |array{param: string, kind: 'option', name: non-empty-string, value: Value, schema: array<string, mixed>, default?: mixed}
 *     |array{param: string, kind: 'flag', name: non-empty-string}
 * @phpstan-type CliSubcommandEntry array{
 *     name: non-empty-string,
 *     description: non-empty-string,
 *     longDescription: string,
 *     method: string,
 *     synopsis: list<array<string, mixed>>,
 *     arguments: list<CliArgument>
 * }
 */
#[NoService]
final readonly class CliCommandDefinition
{
    /**
     * @param non-empty-string         $name
     * @param non-empty-string         $description
     * @param class-string             $class
     * @param list<CliSubcommandEntry> $subcommands
     */
    public function __construct(
        private string $name,
        private string $description,
        private string $class,
        private array $subcommands,
    ) {}

    /**
     * @return non-empty-string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return non-empty-string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return class-string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Subcommands with their WP-CLI synopsis and the method parameters in declaration order.
     *
     * @return list<CliSubcommandEntry>
     */
    public function getSubcommands(): array
    {
        return $this->subcommands;
    }
}
