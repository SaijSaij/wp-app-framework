<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use Attribute;

/**
 * The preview of a block in the inserter. Without this attribute the preview
 * shows the block with its default attributes and, for a container, the
 * template of #[InnerBlocks]; `#[Example(false)]` shows no preview.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Example
{
    /**
     * @param array<string, mixed>|false $attributes    Attribute values (backed enums stand for their value); false
     *                                                  for no preview.
     * @param list<TemplateBlock>        $innerBlocks
     * @param int|null                   $viewportWidth Width in pixels the preview is rendered at (WordPress: 500).
     */
    public function __construct(
        public array|false $attributes = [],
        public array $innerBlocks = [],
        public ?int $viewportWidth = null,
    ) {}
}
