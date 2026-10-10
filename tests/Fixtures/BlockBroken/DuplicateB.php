<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('broken/duplicate', title: 'X', category: BlockCategory::Text)]
final class DuplicateB
{
    public function render(): string
    {
        return "";
    }
}
