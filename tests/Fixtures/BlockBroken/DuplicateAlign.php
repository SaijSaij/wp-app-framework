<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockBroken;

use WebCodingCat\WpAppFramework\Block\Align;
use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Supports;

#[Block('duplicate-align', title: 'X', category: BlockCategory::Text)]
#[Supports(align: [Align::Wide, Align::Wide])]
final class DuplicateAlign
{
    public function render(): string
    {
        return "";
    }
}
