<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('two-post-contents', title: 'TwoPostContents', category: BlockCategory::Text)]
final readonly class TwoPostContents
{
    public function render(TwoPostContentsAttributes $attrs): string
    {
        return '';
    }
}
