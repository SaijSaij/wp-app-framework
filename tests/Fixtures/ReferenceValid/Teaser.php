<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ReferenceValid;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

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
