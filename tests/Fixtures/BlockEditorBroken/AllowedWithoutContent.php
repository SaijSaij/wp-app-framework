<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\InnerBlocks;

#[Block('allowed-without-content', title: 'Allowed', category: BlockCategory::Design)]
#[InnerBlocks(allowed: ['acme/x'])]
final class AllowedWithoutContent
{
    public function render(): string
    {
        return '';
    }
}
