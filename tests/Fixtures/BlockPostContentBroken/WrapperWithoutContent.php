<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('wrapper-without-content', title: 'WrapperWithoutContent', category: BlockCategory::Text, excerptWrapper: true)]
final readonly class WrapperWithoutContent
{
    public function render(): string
    {
        return '';
    }
}
