<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockPostContent;

use WebCodingCat\WpAppFramework\Value\ListOf;
use WebCodingCat\WpAppFramework\Value\PlainText;
use WebCodingCat\WpAppFramework\Value\RichText;

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
