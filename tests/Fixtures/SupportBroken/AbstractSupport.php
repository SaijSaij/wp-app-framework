<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportBroken;

use Saij\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('abstractSupport', label: 'X')]
abstract class AbstractSupport
{
    public function className(bool $value = false): ?string
    {
        return null;
    }
}
