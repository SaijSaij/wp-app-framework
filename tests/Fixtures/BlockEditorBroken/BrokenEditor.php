<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('broken-editor', title: 'Broken editor', category: BlockCategory::Text)]
final class BrokenEditor
{
    public function render(BrokenEditorAttributes $attrs): string
    {
        return '';
    }
}
