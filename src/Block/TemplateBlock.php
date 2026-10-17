<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Block;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * A block with attributes and inner blocks, used in `#[InnerBlocks(template,
 * defaultBlock)]`, `#[Example]` and `#[Variation]`.
 *
 * @api Values and exceptions
 */
#[NoService]
final readonly class TemplateBlock
{
    /**
     * @param class-string|CoreBlock|string $block       A block class of the same theme or plugin, a core block or the
     *                                                   full name of another block, e.g. "acme/slider".
     * @param array<string, mixed>          $attributes  Attribute values; backed enums stand for their value.
     * @param list<TemplateBlock>           $innerBlocks Inner blocks, for a container block.
     */
    public function __construct(
        private string|CoreBlock $block,
        private array $attributes = [],
        private array $innerBlocks = [],
    ) {}

    /**
     * The block: a block class, a core block or the full name of another block.
     *
     * @return class-string|CoreBlock|string
     */
    public function getBlock(): string|CoreBlock
    {
        return $this->block;
    }

    /**
     * The attribute values by attribute name.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * The inner blocks.
     *
     * @return list<TemplateBlock>
     */
    public function getInnerBlocks(): array
    {
        return $this->innerBlocks;
    }
}
