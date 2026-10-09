<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockValid;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Supports;

#[Block('fixture/section', title: 'Section', category: BlockCategory::Design)]
#[Supports(contentRole: true)]
final class Section
{
    public function render(string $content): string
    {
        return '<section>' . $content . '</section>';
    }
}
