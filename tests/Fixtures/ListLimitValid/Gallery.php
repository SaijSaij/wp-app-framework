<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ListLimitValid;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('gallery', title: 'Gallery', category: BlockCategory::Design)]
final class Gallery
{
    public function render(GalleryAttributes $attrs): string
    {
        return '';
    }
}
