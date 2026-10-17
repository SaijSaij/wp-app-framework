<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('surface', label: 'Surface')]
final class Surface
{
    public function className(Tone $value = Tone::Light): string
    {
        return 'surface-' . $value->value;
    }
}
