<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\InnerBlocks;

#[Block('allowed-unknown', title: 'Allowed unknown', category: BlockCategory::Design)]
#[InnerBlocks(allowed: ['missing', 'core/paragraph', 'fixture/teaser'])]
final class AllowedUnknown
{
    public function render(string $content): string
    {
        return $content;
    }
}
