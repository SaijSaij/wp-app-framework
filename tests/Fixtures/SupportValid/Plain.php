<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

/** Without supports, it needs no block_wrapper_attributes(). */
#[Block('plain', title: 'Plain', category: BlockCategory::Design)]
final class Plain
{
    public function render(): string
    {
        return '<div>plain</div>';
    }
}
