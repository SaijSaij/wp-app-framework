<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Markup;

use Saij\WpAppFramework\Value\Link as ContentLink;
use Saij\WpAppFramework\Value\ListOf;
use Saij\WpAppFramework\Value\PlainText;

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
    ) {}
}
