<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ReferenceValid;

use WebCodingCat\WpAppFramework\Value\PostReference;

final readonly class Card
{
    public function __construct(
        #[PostReference]
        public ?int $target = null,
        public string $label = '',
    ) {}
}
