<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('bad-attributes', title: 'X', category: BlockCategory::Text)]
final class BadAttributes
{
    public function render(BrokenAttributes $attrs): string
    {
        return "";
    }
}
