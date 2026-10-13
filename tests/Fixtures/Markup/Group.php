<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Markup;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Supports;

#[Block('group', title: 'Group', category: BlockCategory::Design)]
#[Supports(contentRole: true)]
final readonly class Group
{
    public function render(string $content): string
    {
        return '<div>' . $content . '</div>';
    }
}
