<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RepeaterValid;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\InnerBlocks;
use Saij\WpAppFramework\Block\Repeater;

#[Block('loop', title: 'Loop', category: BlockCategory::Design)]
#[InnerBlocks(allowed: [Card::class])]
final class Loop
{
    public function render(LoopAttributes $attrs, Repeater $repeater): string
    {
        return implode('', array_map(static fn(int $id): string => $repeater->post($id)->getContent(), $attrs->posts));
    }
}
