<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Markup;

use Saij\WpAppFramework\Block\Align;
use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Supports;

#[Block('card', title: 'Card', category: BlockCategory::Text)]
#[Supports(anchor: true, align: [Align::Wide, Align::Full])]
final readonly class Card
{
    public function render(CardAttributes $attrs): string
    {
        return '<div>' . $attrs->title . '</div>';
    }
}
