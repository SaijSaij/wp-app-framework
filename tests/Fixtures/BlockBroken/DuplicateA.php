<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('duplicate', title: 'X', category: BlockCategory::Text)]
final class DuplicateA
{
    public function render(): string
    {
        return "";
    }
}
