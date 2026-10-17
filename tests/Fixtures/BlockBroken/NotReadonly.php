<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('not-readonly', title: 'X', category: BlockCategory::Text)]
final class NotReadonly
{
    public function render(MutableAttributes $attrs): string
    {
        return "";
    }
}
