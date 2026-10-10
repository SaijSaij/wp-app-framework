<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\DependencyInjection\NoService;

#[Block('broken/not-a-service', title: 'X', category: BlockCategory::Text)]
#[NoService]
final class NotAService
{
    public function render(): string
    {
        return "";
    }
}
