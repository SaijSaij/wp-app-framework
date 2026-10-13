<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Markup;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Supports;

#[Block('divider', title: 'Divider', category: BlockCategory::Design)]
#[Supports(customClassName: false, customCSS: false)]
final readonly class Divider
{
    public function render(): string
    {
        return '<hr>';
    }
}
