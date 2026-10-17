<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\Markup;

use WebCodingCat\WpAppFramework\Value\Color;
use WebCodingCat\WpAppFramework\Value\Link as ContentLink;
use WebCodingCat\WpAppFramework\Value\ListOf;
use WebCodingCat\WpAppFramework\Value\PlainText;

final readonly class CardAttributes
{
    /**
     * @param list<Item> $items
     */
    public function __construct(
        #[PlainText]
        public string $title = '',
        public Tone $tone = Tone::Light,
        public ?Link $link = null,
        #[ListOf(Item::class)]
        public array $items = [],
        public int $count = 1,
        public ContentLink $cta = new ContentLink(),
        #[Color]
        public string $accent = '',
    ) {}
}
