<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportBroken;

use Saij\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('twoParameters', label: 'X')]
final class TwoParameters
{
    public function className(bool $value = false, string $other = ''): ?string
    {
        return $value ? $other : null;
    }
}
