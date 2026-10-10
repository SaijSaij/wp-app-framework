<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContent;

use Saij\WpAppFramework\Block\PlainText;
use Saij\WpAppFramework\Block\RichText;
use Saij\WpAppFramework\Value\ListOf;

final readonly class CardAttributes
{
    /**
     * @param list<Item> $items
     */
    public function __construct(
        #[PlainText(excerpt: true)]
        public string $title = '',
        #[PlainText]
        public string $kicker = '',
        #[ListOf(Item::class)]
        public array $items = [],
        public Item $featured = new Item(),
        #[RichText(inspector: true, excerpt: true)]
        public string $summary = '',
    ) {}
}
