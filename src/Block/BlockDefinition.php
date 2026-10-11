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
 * @phpstan-type BlockField array{kind: 'plain'|'rich'|'image', label: string, allowedFormats?: list<string>, inspector?: true, postContent?: true, excerpt?: true}
 * @phpstan-type BlockEditor array{
 *     fields: array<string, BlockField>,
 *     controls: array<string, array<string, mixed>>,
 *     innerBlocks: bool,
 *     postContentField: string|null,
 *     editorScript: string|null,
 *     supports: list<string>
 * }
 * @phpstan-type BlockExcerpt array{fields: array<string, 'plain'|'rich'>, wrapper: bool}
 * @phpstan-type BlockSupportEntry array{attribute: string, class: class-string, value: Value, editorScript: string|null}
 * @phpstan-import-type Value from \Saij\WpAppFramework\Value\ValueFactory
 */
#[NoService]
final readonly class BlockDefinition
{
    /**
     * @param BlockArgs            $args      Arguments for register_block_type() without the render callback.
     * @param class-string         $class
     * @param list<BlockArgument>  $arguments Parameters of render() in declaration order.
     * @param BlockEditor          $editor    Data of the editor runtime: inline fields by path (list items as `*`),
     *                                        inspector controls by attribute, whether the block has inner blocks,
     *                                        the attribute stored in the post content, its editor script below
     *                                        the script directory, without extension, and the attributes of its
     *                                        block supports.
     * @param BlockExcerpt         $excerpt   Text fields included in automatic excerpts (kind by path, list items as
     *                                        `*`) and whether the block is an excerpt wrapper.
     * @param list<BlockSupportEntry> $supports Block supports (`#[UseSupport]`) in declaration order: attribute,
     *                                        service with className(), description of the value and the support's
     *                                        editor script below the script directory, without extension.
     */
    public function __construct(
        private string $name,
        private array $args,
        private string $class,
        private array $arguments,
        private array $editor,
        private array $excerpt,
        private array $supports = [],
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

    /**
     * @return BlockExcerpt
     */
    public function getExcerpt(): array
    {
        return $this->excerpt;
    }

    /**
     * @return list<BlockSupportEntry>
     */
    public function getSupports(): array
    {
        return $this->supports;
    }
}
