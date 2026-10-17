<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockPostContentBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('wrapper-without-content', title: 'WrapperWithoutContent', category: BlockCategory::Text, excerptWrapper: true)]
final readonly class WrapperWithoutContent
{
    public function render(): string
    {
        return '';
    }
}
