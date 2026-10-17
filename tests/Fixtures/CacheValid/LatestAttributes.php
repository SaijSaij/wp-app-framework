<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CacheValid;

final readonly class LatestAttributes
{
    public function __construct(
        public string $title = '',
        public bool $fail = false,
        public bool $enqueue = false,
        public string $data = '',
    ) {}
}
