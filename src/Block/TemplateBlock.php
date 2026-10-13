<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * A block with attributes and inner blocks, used in `#[InnerBlocks(template,
 * defaultBlock)]`, `#[Example]` and `#[Variation]`.
 */
#[NoService]
final readonly class TemplateBlock
{
    /**
     * @param class-string|CoreBlock|string $block       A block class of the same theme or plugin, a core block or the
     *                                                   full name of another block, e.g. "acme/slider".
     * @param array<string, mixed>          $attributes  Attribute values; backed enums stand for their value.
     * @param list<TemplateBlock>           $innerBlocks
     */
    public function __construct(
        private string|CoreBlock $block,
        private array $attributes = [],
        private array $innerBlocks = [],
    ) {}

    /**
     * @return class-string|CoreBlock|string
     */
    public function getBlock(): string|CoreBlock
    {
        return $this->block;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return list<TemplateBlock>
     */
    public function getInnerBlocks(): array
    {
        return $this->innerBlocks;
    }
}
