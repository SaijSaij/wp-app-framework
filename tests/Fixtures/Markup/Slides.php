<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Markup;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\InnerBlocks;
use Saij\WpAppFramework\Block\TemplateBlock;
use Saij\WpAppFramework\Block\TemplateLock;

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
