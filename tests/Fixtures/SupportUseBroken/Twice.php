<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Support\UseSupport;

#[Block('broken/twice', title: 'X', category: BlockCategory::Text)]
#[UseSupport(Surface::class)]
#[UseSupport(Surface::class)]
final class Twice
{
    public function render(): string
    {
        return '';
    }
}
