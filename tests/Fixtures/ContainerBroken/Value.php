<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ContainerBroken;

use Saij\WpAppFramework\Block\ProvidesContext;

final readonly class Value
{
    public function __construct(
        #[ProvidesContext]
        public string $tone = '',
    ) {}
}
