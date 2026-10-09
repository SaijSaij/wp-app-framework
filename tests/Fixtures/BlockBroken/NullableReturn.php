<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Block\Align;
use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Supports;
use Saij\WpAppFramework\DependencyInjection\NoService;
use WP_Block;

#[Block('broken/nullable-return', title: 'X', category: BlockCategory::Text)]
final class NullableReturn
{
    public function render(): ?string
    {
        return null;
    }
}
