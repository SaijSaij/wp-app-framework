<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('Invalid', title: 'X', category: BlockCategory::Text)]
final class InvalidName
{
    public function render(): string
    {
        return "";
    }
}
