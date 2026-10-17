<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('static-render', title: 'X', category: BlockCategory::Text)]
final class StaticRender
{
    public static function render(): string
    {
        return "";
    }
}
