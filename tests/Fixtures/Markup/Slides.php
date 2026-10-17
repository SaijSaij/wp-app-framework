<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\Markup;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\InnerBlocks;
use WebCodingCat\WpAppFramework\Block\TemplateBlock;
use WebCodingCat\WpAppFramework\Block\TemplateLock;

/**
 * A container with a locked template: the editor replaces inner blocks that differ from it.
 */
#[Block('slides', title: 'Slides', category: BlockCategory::Design)]
#[InnerBlocks(allowed: [Slide::class], template: [new TemplateBlock(Slide::class), new TemplateBlock(Slide::class)], templateLock: TemplateLock::All)]
final readonly class Slides
{
    public function render(string $content): string
    {
        return '<div>' . $content . '</div>';
    }
}
