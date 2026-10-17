<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('nested', title: 'Nested', category: BlockCategory::Text)]
final readonly class Nested
{
    public function render(NestedAttributes $attrs): string
    {
        return '';
    }
}
