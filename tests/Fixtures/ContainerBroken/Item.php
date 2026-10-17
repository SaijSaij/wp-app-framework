<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ContainerBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

/** @phpstan-ignore class.notFound (the ancestor is missing on purpose) */
#[Block('item', title: 'Item', category: BlockCategory::Text, ancestor: [Missing::class])]
final class Item
{
    public function render(): string
    {
        return '';
    }
}
