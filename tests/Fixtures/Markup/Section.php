<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Markup;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\CoreBlock;
use Saij\WpAppFramework\Block\InnerBlocks;

/**
 * A container without content: in a pattern or template part nothing can be inserted into it.
 */
#[Block('section', title: 'Section', category: BlockCategory::Design)]
#[InnerBlocks(allowed: [Card::class, CoreBlock::Paragraph])]
final readonly class Section
{
    public function render(string $content): string
    {
        return '<section>' . $content . '</section>';
    }
}
