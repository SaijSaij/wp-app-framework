<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportBroken;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('objectValue', label: 'X')]
final class ObjectValue
{
    public function className(Box $value = new Box()): string
    {
        return $value->name;
    }
}
