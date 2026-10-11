<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ReferenceValid;

use Saij\WpAppFramework\Value\PostReference;

final readonly class Card
{
    public function __construct(
        #[PostReference]
        public ?int $target = null,
        public string $label = '',
    ) {}
}
