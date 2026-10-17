<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockView;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

/** Has a view script (Bare.view.js), but prints its root element without block_wrapper_attributes(). */
#[Block('bare', title: 'Bare', category: BlockCategory::Text)]
final class Bare
{
    public function render(): string
    {
        return '<div>Bare</div>';
    }
}
