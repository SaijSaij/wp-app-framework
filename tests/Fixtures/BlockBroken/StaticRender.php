<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('static-render', title: 'X', category: BlockCategory::Text)]
final class StaticRender
{
    public static function render(): string
    {
        return "";
    }
}
