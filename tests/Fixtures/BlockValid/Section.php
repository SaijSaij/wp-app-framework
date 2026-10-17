<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Supports;

#[Block('section', title: 'Section', category: BlockCategory::Design)]
#[Supports(contentRole: true, customCSS: false)]
final class Section
{
    public function render(string $content): string
    {
        return '<section>' . $content . '</section>';
    }
}
