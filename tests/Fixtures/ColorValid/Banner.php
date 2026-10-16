<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ColorValid;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('banner', title: 'Banner', category: BlockCategory::Design)]
final class Banner
{
    public function render(BannerAttributes $attrs): string
    {
        return $attrs->background;
    }
}
