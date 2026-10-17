<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('duplicate', title: 'X', category: BlockCategory::Text)]
final class DuplicateB
{
    public function render(): string
    {
        return "";
    }
}
