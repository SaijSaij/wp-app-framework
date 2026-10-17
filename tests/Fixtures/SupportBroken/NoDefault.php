<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportBroken;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('noDefault', label: 'X')]
final class NoDefault
{
    public function className(bool $value): ?string
    {
        return $value ? 'x' : null;
    }
}
