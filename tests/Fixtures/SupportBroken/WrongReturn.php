<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportBroken;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('wrongReturn', label: 'X')]
final class WrongReturn
{
    /** @return list<string> */
    public function className(bool $value = false): array
    {
        return [];
    }
}
