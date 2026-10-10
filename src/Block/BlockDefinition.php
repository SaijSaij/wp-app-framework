<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A validated `#[Block]` class.
 *
 * @phpstan-type BlockArgument array{param: string, kind: 'attributes', class: class-string}
 *     |array{param: string, kind: 'content'|'block'}
 * @phpstan-type BlockArgs array{attributes: array<string, array<string, mixed>>, ...<string, mixed>}
 * @phpstan-type BlockField array{kind: 'plain'|'rich'|'image', label: string, allowedFormats?: list<string>, inspector?: true}
 * @phpstan-type BlockEditor array{
 *     fields: array<string, BlockField>,
 *     controls: array<string, array<string, mixed>>,
 *     innerBlocks: bool,
 *     editorScript: string|null
 * }
 */
#[NoService]
final readonly class BlockDefinition
{
    /**
     * @param BlockArgs            $args      Arguments for register_block_type() without the render callback.
     * @param class-string         $class
     * @param list<BlockArgument>  $arguments Parameters of render() in declaration order.
     * @param BlockEditor          $editor    Data of the editor runtime: inline fields by path (list items as `*`),
     *                                        inspector controls by attribute, whether the block has inner blocks
     *                                        and its editor script below the script directory, without extension.
     */
    public function __construct(
        private string $name,
        private array $args,
        private string $class,
        private array $arguments,
        private array $editor,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return BlockArgs
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

    /**
     * @return BlockEditor
     */
    public function getEditor(): array
    {
        return $this->editor;
    }
}
