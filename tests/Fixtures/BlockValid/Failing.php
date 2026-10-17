<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockValid;

use RuntimeException;
use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('failing', title: 'Failing', category: BlockCategory::Widgets)]
final class Failing
{
    public function render(): string
    {
        throw new RuntimeException('fixture block failure');
    }
}
