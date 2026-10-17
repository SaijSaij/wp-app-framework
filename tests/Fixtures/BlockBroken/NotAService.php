<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

#[Block('not-a-service', title: 'X', category: BlockCategory::Text)]
#[NoService]
final class NotAService
{
    public function render(): string
    {
        return "";
    }
}
