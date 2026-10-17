<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportBroken;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('className', label: 'X')]
final class Reserved
{
    public function className(bool $value = false): ?string
    {
        return $value ? 'x' : null;
    }
}
