<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ContainerBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Context;
use Saij\WpAppFramework\Block\Example;
use Saij\WpAppFramework\Block\TemplateBlock;

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
