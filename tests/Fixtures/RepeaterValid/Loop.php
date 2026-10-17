<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\RepeaterValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\InnerBlocks;
use WebCodingCat\WpAppFramework\Block\Repeater;

#[Block('loop', title: 'Loop', category: BlockCategory::Design)]
#[InnerBlocks(allowed: [Card::class])]
final class Loop
{
    public function render(LoopAttributes $attrs, Repeater $repeater): string
    {
        return implode('', array_map(static fn(int $id): string => $repeater->post($id)->getContent(), $attrs->posts));
    }
}
