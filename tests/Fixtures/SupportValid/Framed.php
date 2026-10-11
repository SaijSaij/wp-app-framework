<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportValid;

use Saij\WpAppFramework\Block\Support\BlockSupport;
use Saij\WpAppFramework\Block\Support\SupportGroup;

#[BlockSupport('framed', label: 'Framed', group: SupportGroup::Settings)]
final class Framed
{
    public function className(bool $value = false): ?string
    {
        return $value ? 'is-framed has-frame' : null;
    }
}
