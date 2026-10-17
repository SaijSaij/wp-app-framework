<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('recursive', title: 'X', category: BlockCategory::Text)]
final class Recursive
{
    public function render(RecursiveAttributes $attrs): string
    {
        return "";
    }
}
