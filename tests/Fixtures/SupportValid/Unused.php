<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportValid;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;

/** Used by no block: its unresolvable dependency is never checked. */
#[BlockSupport('unused', label: 'Unused')]
final class Unused
{
    public function __construct(private readonly \Countable $countable) {}

    public function className(int $value = 1): ?string
    {
        return $this->countable->count() > $value ? 'many' : null;
    }
}
