<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\RepeaterBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Context;
use Saij\WpAppFramework\Block\Item;
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
