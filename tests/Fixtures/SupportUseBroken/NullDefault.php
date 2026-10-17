<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Support\UseSupport;

#[Block('null-default', title: 'X', category: BlockCategory::Text)]
#[UseSupport(Flag::class, default: null)]
final class NullDefault
{
    public function render(): string
    {
        return '';
    }
}
