<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use Saij\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('flag', label: 'Flag')]
final class Flag
{
    public function className(bool $value = false): ?string
    {
        return $value ? 'is-flagged' : null;
    }
}
