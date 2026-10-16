<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ListLimitBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

/**
 * A valid attribute class whose list items contain a list with appender: reported by the block collector.
 */
#[Block('nested', title: 'Nested', category: BlockCategory::Design)]
final class Nested
{
    public function render(NestedAttributes $attrs): string
    {
        return '';
    }
}
