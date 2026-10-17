<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ListLimitValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('gallery', title: 'Gallery', category: BlockCategory::Design)]
final class Gallery
{
    public function render(GalleryAttributes $attrs): string
    {
        return '';
    }
}
