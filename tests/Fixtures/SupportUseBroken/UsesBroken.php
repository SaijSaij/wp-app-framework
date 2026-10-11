<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Support\UseSupport;

#[Block('broken/uses-broken', title: 'X', category: BlockCategory::Text)]
#[UseSupport(Broken::class)]
final class UsesBroken
{
    public function render(): string
    {
        return '';
    }
}
