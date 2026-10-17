<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Support\UseSupport;

#[Block('collision', title: 'X', category: BlockCategory::Text)]
#[UseSupport(Surface::class)]
final class Collision
{
    public function render(CollisionAttributes $attrs): string
    {
        return '';
    }
}
