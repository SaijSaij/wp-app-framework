<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('nested', title: 'Nested', category: BlockCategory::Text)]
final readonly class Nested
{
    public function render(NestedAttributes $attrs): string
    {
        return '';
    }
}
