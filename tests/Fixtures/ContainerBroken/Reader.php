<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ContainerBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Context;
use WebCodingCat\WpAppFramework\Block\Example;
use WebCodingCat\WpAppFramework\Block\TemplateBlock;

#[Block('reader', title: 'Reader', category: BlockCategory::Text)]
#[Example(attributes: ['tone' => 5], innerBlocks: [new TemplateBlock('acme/x')])]
final class Reader
{
    public function render(
        #[Context(Box::class, 'tone')]
        string $required,
        ReaderAttributes $attributes,
        #[Context(Box::class, 'size')]
        int $size = 0,
        #[Context(Box::class, 'tone')]
        int $tone = 0,
        #[Context(Box::class, 'width')]
        int $width = 0,
        #[Context(Service::class, 'x')]
        int $other = 0,
    ): string {
        return '';
    }
}
