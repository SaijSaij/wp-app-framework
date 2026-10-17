<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportValid;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;
use WebCodingCat\WpAppFramework\Block\Support\SupportGroup;

#[BlockSupport('framed', label: 'Framed', group: SupportGroup::Settings)]
final class Framed
{
    public function className(bool $value = false): ?string
    {
        return $value ? 'is-framed has-frame' : null;
    }
}
