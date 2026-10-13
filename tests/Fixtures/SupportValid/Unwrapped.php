<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportValid;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Block\Support\UseSupport;

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
