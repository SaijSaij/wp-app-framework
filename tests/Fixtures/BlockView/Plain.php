<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockView;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

/** Neither view scripts nor block supports: needs no block_wrapper_attributes() and loads no script. */
#[Block('fixture/plain', title: 'Plain', category: BlockCategory::Text)]
final class Plain
{
    public function render(): string
    {
        return '<div>Plain</div>';
    }
}
