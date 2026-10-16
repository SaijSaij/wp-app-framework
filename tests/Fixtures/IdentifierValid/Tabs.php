<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\IdentifierValid;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('tabs', title: 'Tabs', category: BlockCategory::Design)]
final class Tabs
{
    public function render(TabsAttributes $attrs): string
    {
        return implode(',', array_map(static fn(Tab $tab): string => $tab->id, $attrs->tabs));
    }
}
