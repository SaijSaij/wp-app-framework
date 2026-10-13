<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ContainerBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

/** @phpstan-ignore class.notFound (the ancestor is missing on purpose) */
#[Block('item', title: 'Item', category: BlockCategory::Text, ancestor: [Missing::class])]
final class Item
{
    public function render(): string
    {
        return '';
    }
}
