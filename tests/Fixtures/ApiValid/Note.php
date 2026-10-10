<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ApiValid;

use Saij\WpAppFramework\DependencyInjection\NoService;

#[NoService]
final readonly class Note
{
    public function __construct(
        public string $text,
    ) {}
}
