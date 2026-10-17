<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Support\UseSupport;

#[Block('unknown-support', title: 'X', category: BlockCategory::Text)]
#[UseSupport(Helper::class)]
final class UnknownSupport
{
    public function render(): string
    {
        return '';
    }
}
