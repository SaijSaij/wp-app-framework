<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Markup;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

/**
 * A container without content: in a pattern or template part nothing can be inserted into it.
 */
#[Block('fixture/section', title: 'Section', category: BlockCategory::Design, allowedBlocks: ['fixture/card', 'core/paragraph'])]
final readonly class Section
{
    public function render(string $content): string
    {
        return '<section>' . $content . '</section>';
    }
}
