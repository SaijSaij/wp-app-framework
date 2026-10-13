<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeValid;

final readonly class Ticket
{
    public function __construct(
        public string $label,
        public float $price = 0.0,
        public ?string $note = null,
    ) {}
}
