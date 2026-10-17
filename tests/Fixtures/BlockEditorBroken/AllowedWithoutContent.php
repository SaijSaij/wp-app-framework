<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\InnerBlocks;

#[Block('allowed-without-content', title: 'Allowed', category: BlockCategory::Design)]
#[InnerBlocks(allowed: ['acme/x'])]
final class AllowedWithoutContent
{
    public function render(): string
    {
        return '';
    }
}
