<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ReferenceValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('teaser', title: 'Teaser', category: BlockCategory::Design)]
final class Teaser
{
    public function render(TeaserAttributes $attrs): string
    {
        return json_encode([
            'page' => $attrs->page,
            'posts' => $attrs->posts,
            'topic' => $attrs->topic,
            'author' => $attrs->author,
            'cards' => array_map(static fn(Card $card): ?int => $card->target, $attrs->cards),
        ], JSON_THROW_ON_ERROR);
    }
}
