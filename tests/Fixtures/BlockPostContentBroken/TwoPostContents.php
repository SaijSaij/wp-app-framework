<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('fixture/two-post-contents', title: 'TwoPostContents', category: BlockCategory::Text)]
final readonly class TwoPostContents
{
    public function render(TwoPostContentsAttributes $attrs): string
    {
        return '';
    }
}
