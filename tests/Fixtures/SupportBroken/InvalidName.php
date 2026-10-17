<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportBroken;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('Color-Scheme', label: 'X')]
final class InvalidName
{
    public function className(bool $value = false): ?string
    {
        return $value ? 'x' : null;
    }
}
