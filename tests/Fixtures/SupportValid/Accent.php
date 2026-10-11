<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportValid;

use Saij\WpAppFramework\Block\Support\BlockSupport;

/** Returns the value as class name, unchecked: invalid values make invalid class names. */
#[BlockSupport('accent', label: 'Accent')]
final class Accent
{
    public function className(?string $value = null): string
    {
        return $value ?? '';
    }
}
