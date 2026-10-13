<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ContainerBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('peeker', title: 'Peeker', category: BlockCategory::Text, usesContext: ['fixture/box/size'])]
final class Peeker
{
    public function render(): string
    {
        return '';
    }
}
