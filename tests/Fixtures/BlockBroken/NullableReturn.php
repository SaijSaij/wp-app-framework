<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\BlockBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;

#[Block('broken/nullable-return', title: 'X', category: BlockCategory::Text)]
final class NullableReturn
{
    // @phpstan-ignore return.unusedType (fixture: the nullable return type is the error under test)
    public function render(): ?string
    {
        return null;
    }
}
