<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('core/paragraph', title: 'X', category: BlockCategory::Text)]
final class CoreName
{
    public function render(): string
    {
        return "";
    }
}
