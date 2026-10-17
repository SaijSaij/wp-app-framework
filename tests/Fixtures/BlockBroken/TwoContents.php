<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('two-contents', title: 'X', category: BlockCategory::Text)]
final class TwoContents
{
    public function render(string $first, string $second): string
    {
        return "";
    }
}
