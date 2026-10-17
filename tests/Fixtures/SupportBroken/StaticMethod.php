<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportBroken;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('staticMethod', label: 'X')]
final class StaticMethod
{
    public static function className(bool $value = false): ?string
    {
        return $value ? 'x' : null;
    }
}
