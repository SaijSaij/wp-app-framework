<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockPostContent;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('wrapper', title: 'Wrapper', category: BlockCategory::Design, excerptWrapper: true)]
final readonly class Wrapper
{
    public function render(string $content): string
    {
        return '<div>' . $content . '</div>';
    }
}
