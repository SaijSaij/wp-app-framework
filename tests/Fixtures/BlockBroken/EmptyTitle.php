<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('empty-title', title: ' ', category: BlockCategory::Text)]
final class EmptyTitle
{
    public function render(): string
    {
        return "";
    }
}
