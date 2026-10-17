<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\RepeaterBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Repeater;

#[Block('both', title: 'Both', category: BlockCategory::Design)]
final class Both
{
    public function render(string $content, Repeater $repeater): string
    {
        return $content;
    }
}
