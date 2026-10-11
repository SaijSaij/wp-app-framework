<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportBroken;

use Saij\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('objectValue', label: 'X')]
final class ObjectValue
{
    public function className(Box $value = new Box()): string
    {
        return $value->name;
    }
}
