<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Markup;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('slide', title: 'Slide', category: BlockCategory::Design, parent: [Slides::class])]
final readonly class Slide
{
    public function render(string $content): string
    {
        return '<div>' . $content . '</div>';
    }
}
