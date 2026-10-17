<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportBroken;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;
use WebCodingCat\WpAppFramework\Value\Label;

#[BlockSupport('withAttribute', label: 'X')]
final class WithAttribute
{
    public function className(#[Label('Other')] bool $value = false): ?string
    {
        return $value ? 'x' : null;
    }
}
