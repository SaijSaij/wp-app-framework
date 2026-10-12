<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\Markup;

final readonly class Link
{
    public function __construct(
        public string $href,
        public bool $external = false,
    ) {}
}
