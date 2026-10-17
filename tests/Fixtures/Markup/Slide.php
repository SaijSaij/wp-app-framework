<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\Markup;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('slide', title: 'Slide', category: BlockCategory::Design, parent: [Slides::class])]
final readonly class Slide
{
    public function render(string $content): string
    {
        return '<div>' . $content . '</div>';
    }
}
