<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('empty-keyword', title: 'X', category: BlockCategory::Text, keywords: [''])]
final class EmptyKeyword
{
    public function render(): string
    {
        return "";
    }
}
