<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Markup;

use Saij\WpAppFramework\Block\PlainText;
use Saij\WpAppFramework\Value\ListOf;

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
    ) {}
}
