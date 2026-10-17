<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('flag', label: 'Flag')]
final class Flag
{
    public function className(bool $value = false): ?string
    {
        return $value ? 'is-flagged' : null;
    }
}
