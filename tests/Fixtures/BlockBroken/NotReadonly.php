<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('not-readonly', title: 'X', category: BlockCategory::Text)]
final class NotReadonly
{
    public function render(MutableAttributes $attrs): string
    {
        return "";
    }
}
