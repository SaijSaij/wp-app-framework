<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('broken-editor', title: 'Broken editor', category: BlockCategory::Text)]
final class BrokenEditor
{
    public function render(BrokenEditorAttributes $attrs): string
    {
        return '';
    }
}
