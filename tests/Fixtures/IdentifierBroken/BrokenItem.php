<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\IdentifierBroken;

use WebCodingCat\WpAppFramework\Value\Hidden;
use WebCodingCat\WpAppFramework\Value\Identifier;
use WebCodingCat\WpAppFramework\Value\PlainText;

final readonly class BrokenItem
{
    public function __construct(
        #[Identifier]
        public int $number = 0,
        #[Identifier]
        public ?string $nullable = null,
        #[Identifier]
        #[PlainText]
        public string $plain = '',
        #[Identifier]
        #[Hidden]
        public string $hidden = '',
        #[Identifier]
        public string $preset = '0a1b2c3d',
    ) {}
}
