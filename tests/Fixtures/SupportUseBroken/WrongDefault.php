<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Support\UseSupport;

#[Block('wrong-default', title: 'X', category: BlockCategory::Text)]
#[UseSupport(Surface::class, default: 'dark')]
final class WrongDefault
{
    public function render(): string
    {
        return '';
    }
}
