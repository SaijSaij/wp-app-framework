<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Support\UseSupport;

#[Block('unknown-support', title: 'X', category: BlockCategory::Text)]
#[UseSupport(Helper::class)]
final class UnknownSupport
{
    public function render(): string
    {
        return '';
    }
}
