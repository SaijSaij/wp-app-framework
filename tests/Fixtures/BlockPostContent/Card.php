<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockPostContent;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('card', title: 'Card', category: BlockCategory::Text)]
final readonly class Card
{
    public function render(CardAttributes $attrs): string
    {
        return '<div>card ' . $attrs->title . '</div>';
    }
}
