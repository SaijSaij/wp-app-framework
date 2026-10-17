<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\DateTimeValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('concert', title: 'Concert', category: BlockCategory::Widgets)]
final class Concert
{
    public function render(ConcertAttributes $attrs): string
    {
        return $attrs->start;
    }
}
