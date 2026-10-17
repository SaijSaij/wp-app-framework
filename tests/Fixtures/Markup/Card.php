<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\Markup;

use WebCodingCat\WpAppFramework\Block\Align;
use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Supports;

#[Block('card', title: 'Card', category: BlockCategory::Text)]
#[Supports(anchor: true, align: [Align::Wide, Align::Full])]
final readonly class Card
{
    public function render(CardAttributes $attrs): string
    {
        return '<div>' . $attrs->title . '</div>';
    }
}
