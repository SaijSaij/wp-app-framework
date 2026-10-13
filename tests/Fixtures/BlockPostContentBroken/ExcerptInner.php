<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('excerpt-inner', title: 'ExcerptInner', category: BlockCategory::Text)]
final readonly class ExcerptInner
{
    public function render(ExcerptInnerAttributes $attrs, string $content): string
    {
        return '';
    }
}
