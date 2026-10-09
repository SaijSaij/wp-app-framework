<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('fixture/allowed-without-content', title: 'Allowed', category: BlockCategory::Design, allowedBlocks: ['fixture/x'])]
final class AllowedWithoutContent
{
    public function render(): string
    {
        return '';
    }
}
