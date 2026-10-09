<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A validated `#[Block]` class.
 *
 * @phpstan-type BlockArgument array{param: string, kind: 'attributes', class: class-string}
 *     |array{param: string, kind: 'content'|'block'}
 */
#[NoService]
final readonly class BlockDefinition
{
    /**
     * @param array<string, mixed> $args      Arguments for register_block_type() without the render callback.
     * @param class-string         $class
     * @param list<BlockArgument>  $arguments Parameters of render() in declaration order.
     */
    public function __construct(
        private string $name,
        private array $args,
        private string $class,
        private array $arguments,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @return class-string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @return list<BlockArgument>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
