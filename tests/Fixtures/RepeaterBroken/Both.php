<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RepeaterBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Repeater;

#[Block('both', title: 'Both', category: BlockCategory::Design)]
final class Both
{
    public function render(string $content, Repeater $repeater): string
    {
        return $content;
    }
}
