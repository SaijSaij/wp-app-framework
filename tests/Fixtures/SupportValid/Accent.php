<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportValid;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;

/** Returns the value as class name, unchecked: invalid values make invalid class names. */
#[BlockSupport('accent', label: 'Accent')]
final class Accent
{
    public function className(?string $value = null): string
    {
        return $value ?? '';
    }
}
