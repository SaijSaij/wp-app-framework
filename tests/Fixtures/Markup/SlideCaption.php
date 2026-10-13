<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Markup;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('slide-caption', title: 'Slide caption', category: BlockCategory::Text, ancestor: [Slides::class])]
final readonly class SlideCaption
{
    public function render(): string
    {
        return '<p>caption</p>';
    }
}
