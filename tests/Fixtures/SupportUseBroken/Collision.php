<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Support\UseSupport;

#[Block('broken/collision', title: 'X', category: BlockCategory::Text)]
#[UseSupport(Surface::class)]
final class Collision
{
    public function render(CollisionAttributes $attrs): string
    {
        return '';
    }
}
