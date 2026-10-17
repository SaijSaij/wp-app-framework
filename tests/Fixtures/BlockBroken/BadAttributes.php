<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('bad-attributes', title: 'X', category: BlockCategory::Text)]
final class BadAttributes
{
    public function render(BrokenAttributes $attrs): string
    {
        return "";
    }
}
