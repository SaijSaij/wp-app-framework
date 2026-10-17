<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CronValid;

final readonly class Recipient
{
    public function __construct(
        public string $email,
        public ?string $name = null,
    ) {}
}
