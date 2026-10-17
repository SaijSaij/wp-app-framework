<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportValid;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Block\Support\UseSupport;

/** Uses a support, but prints its root element without block_wrapper_attributes(). */
#[Block('unwrapped', title: 'Unwrapped', category: BlockCategory::Design)]
#[UseSupport(Framed::class, default: true)]
final class Unwrapped
{
    public function render(): string
    {
        return '<div>unwrapped</div>';
    }
}
