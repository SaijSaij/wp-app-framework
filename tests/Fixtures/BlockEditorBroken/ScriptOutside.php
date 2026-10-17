<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('script-outside', title: 'Script outside', category: BlockCategory::Text)]
final class ScriptOutside
{
    public function render(): string
    {
        return '';
    }
}
