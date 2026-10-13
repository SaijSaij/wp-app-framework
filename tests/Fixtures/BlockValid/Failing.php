<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockValid;

use RuntimeException;
use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('failing', title: 'Failing', category: BlockCategory::Widgets)]
final class Failing
{
    public function render(): string
    {
        throw new RuntimeException('fixture block failure');
    }
}
