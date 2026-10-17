<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ColorValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('banner', title: 'Banner', category: BlockCategory::Design)]
final class Banner
{
    public function render(BannerAttributes $attrs): string
    {
        return $attrs->background;
    }
}
