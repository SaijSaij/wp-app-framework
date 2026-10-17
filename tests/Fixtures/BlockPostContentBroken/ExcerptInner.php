<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('excerpt-inner', title: 'ExcerptInner', category: BlockCategory::Text)]
final readonly class ExcerptInner
{
    public function render(ExcerptInnerAttributes $attrs, string $content): string
    {
        return '';
    }
}
