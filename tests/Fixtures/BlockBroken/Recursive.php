<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('broken/recursive', title: 'X', category: BlockCategory::Text)]
final class Recursive
{
    public function render(RecursiveAttributes $attrs): string
    {
        return "";
    }
}
