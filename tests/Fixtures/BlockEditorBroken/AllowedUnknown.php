<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('allowed-unknown', title: 'Allowed unknown', category: BlockCategory::Design, allowedBlocks: ['missing', 'core/paragraph'])]
final class AllowedUnknown
{
    public function render(string $content): string
    {
        return $content;
    }
}
