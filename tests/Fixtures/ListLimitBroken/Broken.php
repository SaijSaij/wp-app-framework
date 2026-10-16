<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ListLimitBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('broken', title: 'Broken', category: BlockCategory::Design)]
final class Broken
{
    public function render(BrokenAttributes $attrs): string
    {
        return '';
    }
}
