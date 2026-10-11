<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportBroken;

use Saij\WpAppFramework\Block\Support\BlockSupport;

#[BlockSupport('emptyLabel', label: ' ')]
final class EmptyLabel
{
    public function className(bool $value = false): ?string
    {
        return $value ? 'x' : null;
    }
}
