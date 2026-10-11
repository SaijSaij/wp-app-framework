<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Support\UseSupport;

#[Block('broken/wrong-default', title: 'X', category: BlockCategory::Text)]
#[UseSupport(Surface::class, default: 'dark')]
final class WrongDefault
{
    public function render(): string
    {
        return '';
    }
}
