<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\IdentifierBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('top', title: 'Top', category: BlockCategory::Design)]
final class Top
{
    public function render(TopAttributes $attrs): string
    {
        return $attrs->id;
    }
}
