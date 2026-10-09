<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockEditorBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('fixture/script-outside', title: 'Script outside', category: BlockCategory::Text)]
final class ScriptOutside
{
    public function render(): string
    {
        return '';
    }
}
