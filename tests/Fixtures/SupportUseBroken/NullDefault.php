<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Support\UseSupport;

#[Block('null-default', title: 'X', category: BlockCategory::Text)]
#[UseSupport(Flag::class, default: null)]
final class NullDefault
{
    public function render(): string
    {
        return '';
    }
}
