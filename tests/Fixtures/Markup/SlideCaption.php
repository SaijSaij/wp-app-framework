<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\Markup;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('slide-caption', title: 'Slide caption', category: BlockCategory::Text, ancestor: [Slides::class])]
final readonly class SlideCaption
{
    public function render(): string
    {
        return '<p>caption</p>';
    }
}
