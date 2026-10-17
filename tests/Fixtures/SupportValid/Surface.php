<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportValid;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('surface', label: 'Surface')]
final class Surface
{
    public function __construct(private readonly Naming $naming) {}

    public function className(Tone $value = Tone::Light): string
    {
        return $this->naming->className('surface', $value->value);
    }
}
