<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('two-contents', title: 'X', category: BlockCategory::Text)]
final class TwoContents
{
    public function render(string $first, string $second): string
    {
        return "";
    }
}
