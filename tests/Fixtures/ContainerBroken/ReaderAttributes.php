<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ContainerBroken;

final readonly class ReaderAttributes
{
    public function __construct(
        public string $tone = '',
    ) {}
}
