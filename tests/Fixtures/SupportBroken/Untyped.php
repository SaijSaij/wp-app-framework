<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportBroken;

use Saij\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('untyped', label: 'X')]
final class Untyped
{
    // @phpstan-ignore missingType.parameter (wrong on purpose: the value needs a type)
    public function className($value = false): ?string
    {
        return $value ? 'x' : null;
    }
}
