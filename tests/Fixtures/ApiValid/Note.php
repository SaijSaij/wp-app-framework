<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ApiValid;

use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

#[NoService]
final readonly class Note
{
    public function __construct(
        public string $text,
    ) {}
}
