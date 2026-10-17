<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\Markup;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\CoreBlock;
use WebCodingCat\WpAppFramework\Block\InnerBlocks;

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
