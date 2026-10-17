<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ReferenceBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('broken', title: 'Broken', category: BlockCategory::Design)]
final class Broken
{
    public function render(BrokenAttributes $attrs): string
    {
        return '';
    }
}
