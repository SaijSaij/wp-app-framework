<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\IdentifierValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('tabs', title: 'Tabs', category: BlockCategory::Design)]
final class Tabs
{
    public function render(TabsAttributes $attrs): string
    {
        return implode(',', array_map(static fn(Tab $tab): string => $tab->id, $attrs->tabs));
    }
}
