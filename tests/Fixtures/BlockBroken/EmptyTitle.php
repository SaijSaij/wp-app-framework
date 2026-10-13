<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('empty-title', title: ' ', category: BlockCategory::Text)]
final class EmptyTitle
{
    public function render(): string
    {
        return "";
    }
}
