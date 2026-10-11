<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportBroken;

use Saij\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('noDefault', label: 'X')]
final class NoDefault
{
    public function className(bool $value): ?string
    {
        return $value ? 'x' : null;
    }
}
