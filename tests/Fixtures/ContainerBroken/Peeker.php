<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ContainerBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('peeker', title: 'Peeker', category: BlockCategory::Text, usesContext: ['fixture/box/size'])]
final class Peeker
{
    public function render(): string
    {
        return '';
    }
}
