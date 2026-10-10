<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Block\Align;
use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Supports;

#[Block('broken/duplicate-align', title: 'X', category: BlockCategory::Text)]
#[Supports(align: [Align::Wide, Align::Wide])]
final class DuplicateAlign
{
    public function render(): string
    {
        return "";
    }
}
