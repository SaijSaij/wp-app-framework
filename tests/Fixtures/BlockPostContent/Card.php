<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContent;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('card', title: 'Card', category: BlockCategory::Text)]
final readonly class Card
{
    public function render(CardAttributes $attrs): string
    {
        return '<div>card ' . $attrs->title . '</div>';
    }
}
