<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockPostContent;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('fixture/wrapper', title: 'Wrapper', category: BlockCategory::Design, excerptWrapper: true)]
final readonly class Wrapper
{
    public function render(string $content): string
    {
        return '<div>' . $content . '</div>';
    }
}
