<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportBroken;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('abstractSupport', label: 'X')]
abstract class AbstractSupport
{
    public function className(bool $value = false): ?string
    {
        return null;
    }
}
