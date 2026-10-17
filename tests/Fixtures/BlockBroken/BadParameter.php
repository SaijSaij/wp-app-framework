<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WP_Block;

#[Block('bad-parameter', title: 'X', category: BlockCategory::Text)]
final class BadParameter
{
    public function render(int $number, ?WP_Block $block): string
    {
        return "";
    }
}
