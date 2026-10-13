<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ContainerBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\InnerBlocks;

#[Block('direct', title: 'Direct', category: BlockCategory::Text)]
#[InnerBlocks(allowed: ['core/paragraph'], directInsert: true)]
final class Direct
{
    public function render(string $content): string
    {
        return $content;
    }
}
