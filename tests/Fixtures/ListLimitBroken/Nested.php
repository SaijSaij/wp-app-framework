<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ListLimitBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

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
