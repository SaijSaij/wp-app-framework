<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use WP_Block;

#[Block('bad-parameter', title: 'X', category: BlockCategory::Text)]
final class BadParameter
{
    public function render(int $number, ?WP_Block $block): string
    {
        return "";
    }
}
