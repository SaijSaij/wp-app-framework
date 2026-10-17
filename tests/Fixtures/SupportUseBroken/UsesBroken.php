<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Support\UseSupport;

#[Block('uses-broken', title: 'X', category: BlockCategory::Text)]
#[UseSupport(Broken::class)]
final class UsesBroken
{
    public function render(): string
    {
        return '';
    }
}
