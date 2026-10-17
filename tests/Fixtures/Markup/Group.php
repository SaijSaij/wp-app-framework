<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\Markup;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Supports;

#[Block('group', title: 'Group', category: BlockCategory::Design)]
#[Supports(contentRole: true)]
final readonly class Group
{
    public function render(string $content): string
    {
        return '<div>' . $content . '</div>';
    }
}
