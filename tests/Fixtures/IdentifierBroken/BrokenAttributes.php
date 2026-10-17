<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\IdentifierBroken;

use WebCodingCat\WpAppFramework\Value\ListOf;

final readonly class BrokenAttributes
{
    /**
     * @param list<BrokenItem> $broken
     * @param list<TwoIdentifiers> $two
     * @param list<Item> $invalid
     * @param list<Item> $twice
     */
    public function __construct(
        #[ListOf(BrokenItem::class)]
        public array $broken = [],
        #[ListOf(TwoIdentifiers::class)]
        public array $two = [],
        public Single $single = new Single(),
        #[ListOf(Item::class)]
        public array $invalid = [new Item('ABC')],
        #[ListOf(Item::class)]
        public array $twice = [new Item('0a1b2c3d'), new Item('0a1b2c3d')],
    ) {}
}
