<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportBroken;

use Saij\WpAppFramework\Block\Support\BlockSupport;
use Saij\WpAppFramework\Value\Label;

#[BlockSupport('withAttribute', label: 'X')]
final class WithAttribute
{
    public function className(#[Label('Other')] bool $value = false): ?string
    {
        return $value ? 'x' : null;
    }
}
