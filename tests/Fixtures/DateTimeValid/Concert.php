<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\DateTimeValid;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('concert', title: 'Concert', category: BlockCategory::Widgets)]
final class Concert
{
    public function render(ConcertAttributes $attrs): string
    {
        return $attrs->start;
    }
}
