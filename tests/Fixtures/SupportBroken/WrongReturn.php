<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportBroken;

use Saij\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('wrongReturn', label: 'X')]
final class WrongReturn
{
    /** @return list<string> */
    public function className(bool $value = false): array
    {
        return [];
    }
}
