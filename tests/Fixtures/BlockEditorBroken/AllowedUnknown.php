<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\InnerBlocks;

#[Block('allowed-unknown', title: 'Allowed unknown', category: BlockCategory::Design)]
#[InnerBlocks(allowed: ['missing', 'core/paragraph', 'fixture/teaser'])]
final class AllowedUnknown
{
    public function render(string $content): string
    {
        return $content;
    }
}
