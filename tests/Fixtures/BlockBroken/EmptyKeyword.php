<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('empty-keyword', title: 'X', category: BlockCategory::Text, keywords: [''])]
final class EmptyKeyword
{
    public function render(): string
    {
        return "";
    }
}
