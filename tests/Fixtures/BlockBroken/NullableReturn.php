<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\BlockBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;

#[Block('nullable-return', title: 'X', category: BlockCategory::Text)]
final class NullableReturn
{
    // @phpstan-ignore return.unusedType (fixture: the nullable return type is the error under test)
    public function render(): ?string
    {
        return null;
    }
}
