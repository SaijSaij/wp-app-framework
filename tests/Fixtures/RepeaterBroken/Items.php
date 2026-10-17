<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\RepeaterBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Context;
use WebCodingCat\WpAppFramework\Block\Item;
use WP_Post;

#[Block('items', title: 'Items', category: BlockCategory::Design)]
final class Items
{
    public function render(
        #[Item]
        WP_Post $required,
        #[Item]
        ?string $text,
        #[Item]
        #[Context(Wrapper::class, 'mode')]
        ?WP_Post $both,
        #[Item]
        ?\DateTimeImmutable $other,
    ): string {
        return '';
    }
}
