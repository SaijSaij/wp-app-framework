<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockView;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

/** Neither view scripts nor block supports: needs no block_wrapper_attributes() and loads no script. */
#[Block('plain', title: 'Plain', category: BlockCategory::Text)]
final class Plain
{
    public function render(): string
    {
        return '<div>Plain</div>';
    }
}
