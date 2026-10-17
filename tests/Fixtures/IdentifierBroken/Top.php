<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\IdentifierBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('top', title: 'Top', category: BlockCategory::Design)]
final class Top
{
    public function render(TopAttributes $attrs): string
    {
        return $attrs->id;
    }
}
