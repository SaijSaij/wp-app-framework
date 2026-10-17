<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportBroken;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('twoParameters', label: 'X')]
final class TwoParameters
{
    public function className(bool $value = false, string $other = ''): ?string
    {
        return $value ? $other : null;
    }
}
